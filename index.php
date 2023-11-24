<?php
// index.php - Main entry point for the VoiceNote Sync application

// Define a global variable for the domain
// Use $_SERVER superglobal to dynamically determine the domain
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain = $protocol . $_SERVER['HTTP_HOST'] . "/VoiceNoteSync/";
define('DOMAIN', $domain);


interface StorageAdapterInterface
{
    public function saveNote($type, $content, &$secretKey);

    public function retrieveNotes($secretKey);
}


class CSVStorageAdapter implements StorageAdapterInterface
{
    private $filePath;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;

        // Check if the file exists, if not, create an empty file
        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, ''); // Create an empty file
        }
    }

    public function saveNote($type, $content, &$secretKey)
    {
        // Generate a new secret key if none is provided
        if (empty($secretKey)) {
            $secretKey = $this->generateSecretKey();
        }

        // Get current date and time in a specific format, e.g., "Y-m-d H:i:s"
        $currentDateTime = date('Y-m-d H:i:s');

        $fileHandle = fopen($this->filePath, 'a'); // Open file in append mode
        // Add the current date and time as a new column in the CSV
        fputcsv($fileHandle, [$type, $content, $secretKey, $currentDateTime]);
        fclose($fileHandle);
    }

    public function retrieveNotes($secretKey): array
    {
        // Check if the file is empty before trying to read it
        if (filesize($this->filePath) == 0) {
            return [];
        }

        $fileHandle = fopen($this->filePath, 'r');
        $rows = [];
        while (($data = fgetcsv($fileHandle)) !== FALSE) {
            if ($data[2] === $secretKey || $secretKey == 'all') {
                $rows[] = $data;
            }
        }
        fclose($fileHandle);


        // Reverse the array
        return array_reverse($rows);
    }

    public function generateSecretKey(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Exception $e) {
        } // Generate a random 32-character hex string
    }
}


function validateNoteData($data): array
{
    // Check if required fields are present
    if (!isset($data['content'])) {
        return ['valid' => false, 'message' => 'Note content is required.'];
    }


    // Additional validation for calendar notes
    if (isset($data['type']) && $data['type'] == 'calendar') {
        // Decode the JSON-encoded ICS data from the content
        $icsData = json_decode($data['content'], true);

        // Validate essential ICS fields
        if (!isset($icsData['DTSTART']) || !isset($icsData['DTEND']) || !isset($icsData['SUMMARY'])) {
            return ['valid' => false, 'message' => 'DTSTART, DTEND, and SUMMARY are required for calendar notes.'];
        }
    }

    // More validation rules can be added as needed

    // If all checks pass
    return ['valid' => true, 'message' => 'Validation successful.'];
}

function formatNoteForDisplay($note): string
{
    $formattedNote = '<div class="card mb-3">';

    // Note type (e.g., text, calendar, etc.)
    $noteType = $note[0];

    // Note content
    $noteContent = $note[1];


    $noteDate = ' Unknown ';
    // Date added
    if (isset($note[3])) {
        $noteDate = $note[3]; // Assuming the date is at index 3
    }

    if ($noteType === 'calendar') {
        // Parse JSON content for calendar notes
        $calendarDetails = json_decode($noteContent, true);
        if ($calendarDetails) {
            $formattedNote .= '<div class="card-header">Calendar Event</div>';
            $formattedNote .= '<div class="card-body">';
            foreach ($calendarDetails as $key => $value) {
                $formattedNote .= '<p class="card-text"><b>' . htmlspecialchars(ucfirst($key)) . ':</b> ' . htmlspecialchars($value) . '</p>';
            }

        } else {
            $formattedNote .= '<div class="card-header">Calendar Event - Old version</div>';
            $formattedNote .= '<div class="card-body">';
            $formattedNote .= '<p class="card-text">' . htmlspecialchars($noteContent) . '</p>';
        }
    } else {
        // For other note types
        $formattedNote .= '<div class="card-header">' . htmlspecialchars($noteType) . '</div>';
        $formattedNote .= '<div class="card-body">';
        $formattedNote .= '<p class="card-text">' . htmlspecialchars($noteContent) . '</p>';
    }

    // Add the date to the note display
    $formattedNote .= '<p class="text-muted">Date Added: ' . htmlspecialchars($noteDate) . '</p>';
    $formattedNote .= '</div>';
    $formattedNote .= '</div>';


    return $formattedNote;
}


function handleNoteCreation($type, $data, $secretKey, CSVStorageAdapter $storage)
{
    // Validate the note data first
    $validationResult = validateNoteData($data);
    if (!$validationResult['valid']) {
        return json_encode(['status' => 'error', 'message' => $validationResult['message']]);
    }

    // Save the note using the storage adapter
    $storage->saveNote($type, $data['content'], $secretKey);

    return json_encode([
        'status' => 'success',
        'message' => 'Note created successfully',
        'secretKey' => $secretKey,
        'viewNotesLinkBrowser' => DOMAIN . '?secretKey=' . $secretKey, // For viewing in a web browser
        'viewNotesLinkJSON' => DOMAIN . '?type=getNotes&secretKey=' . $secretKey // For API access, returning JSON
    ]);
}

function handleNoteRetrieval($secretKey, CSVStorageAdapter $storage)
{
    $notes = $storage->retrieveNotes($secretKey);

    // Format notes for display or API response
    $formattedNotes = array_map('formatNoteForDisplay', $notes);

    return json_encode([
        'status' => 'success',
        'notes' => $formattedNotes,
        'secretKey' => $secretKey,
        'viewNotesLinkBrowser' => DOMAIN . '?secretKey=' . $secretKey, // For viewing in a web browser
        'viewNotesLinkJSON' => DOMAIN . '?type=getNotes&secretKey=' . $secretKey // For API access, returning JSON
    ]);
}


// Create an instance of CSVStorageAdapter
$storage = new CSVStorageAdapter('notes.csv');

// Get the request type and parameters
$requestType = $_GET['type'] ?? '';
$secretKey = $_GET['secretKey'] ?? '';

// Check if a new secret key needs to be generated
$newKeyGenerated = false;
if (empty($secretKey)) {
    $newKeyGenerated = true;
    $secretKey = $storage->generateSecretKey(); // Generate a new secret key
}

// Handle API requests
$response = [];
if ($requestType) {
    switch ($requestType) {
        case 'text':
        case 'calendar':
        case 'dalleImage':
        case 'voiceNote':
            $response = json_decode(handleNoteCreation($requestType, $_GET, $secretKey, $storage), true);
            break;
        case 'getNotes':
            $response = json_decode(handleNoteRetrieval($secretKey, $storage), true);
            break;
        default:
            $response = ['error' => 'Invalid request type'];
    }

    // If a new secret key was generated, add this info to the response
    if ($newKeyGenerated) {
        $response = array_merge($response, [
            'newKeyGenerated' => true,
            'message' => 'New secret key generated. Please use this key for future requests.',
            'secretKey' => $secretKey,
            'viewNotesLinkBrowser' => DOMAIN . '?secretKey=' . $secretKey, // For viewing in a web browser
            'viewNotesLinkJSON' => DOMAIN . '?type=getNotes&secretKey=' . $secretKey // For API access, returning JSON
        ]);
    }

    $response = array_merge($response, [
        'commentsForChatGPT' =>
            "newKeyGenerated: Boolean indicating whether a new secret key was generated during the request.\n" .
            "message: String providing a message related to the request, such as information on new key usage.\n" .
            "secretKey: String representing the secret key associated with the user's notes, either newly generated or existing.\n" .
            "viewNotesLinkBrowser: URL for viewing the notes in a web browser, dynamically including the secret key.\n" .
            "viewNotesLinkJSON: URL for API access to retrieve notes in JSON format, includes the secret key parameter.\n" .
            "examples: Array of example URLs for creating different types of notes, each URL includes note type, parameters, and secret key.\n" .
            "  - createTextNote: URL for creating a text note.\n" .
            "  - createCalendarEvent: URL for creating a calendar event.\n" .
            "  - saveDalleImagePrompt: URL for saving a DALL·E image prompt.\n" .
            "  - createVoiceNote: URL for creating a voice note.\n" .
            "error: String indicating an error, present if the request type is invalid, with error details."
    ]);

    echo json_encode($response);
    exit;

}

// Web request - Display notes using a template
$notes = $storage->retrieveNotes($secretKey);
$formattedNotes = array_map('formatNoteForDisplay', $notes);

// If a new secret key was generated, add this info and examples for creating notes
if ($newKeyGenerated) {
    $response = [
        'newKeyGenerated' => true,
        'message' => 'New secret key generated. Please use this key for future requests.',
        'secretKey' => $secretKey,
        'viewNotesLinkBrowser' => DOMAIN . '?secretKey=' . $secretKey, // For viewing in a web browser
        'viewNotesLinkJSON' => DOMAIN . '?type=getNotes&secretKey=' . $secretKey, // For API access, returning JSON
        'examples' => [
            'createTextNote' => DOMAIN . '?type=text&content=YourTextContent&secretKey=' . $secretKey,
            'createCalendarEvent' => DOMAIN . '?type=calendar&date=YYYY-MM-DD&time=HH:MM&content=EventDetails&secretKey=' . $secretKey,
            'saveDalleImagePrompt' => DOMAIN . '?type=dalleImage&prompt=ImagePrompt&secretKey=' . $secretKey,
            'createVoiceNote' => DOMAIN . '?type=voiceNote&content=TranscribedVoiceContent&secretKey=' . $secretKey
        ]
    ];
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enigma - VoiceNote Sync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

</head>
<body>
<div class="container mt-4">
    <h1 class="mb-4">Enigma - VoiceNote Sync Notes</h1>

    <!-- Check if a new secret key was generated -->
    <?php if (!empty($response['newKeyGenerated']) && $response['newKeyGenerated']): ?>

        <div class="container mt-4">
            <h1>ChatGPT Guide for Enigma - VoiceNote Sync</h1>

            <h2>Introduction</h2>
            <p>"Enigma - VoiceNote Sync" is a sophisticated note-taking system specifically designed to work with ChatGPT, particularly for voice interactions. This system facilitates the creation and retrieval of notes through GET requests, making it versatile for various applications.</p>

            <h2>URL Structure</h2>
            <p>The primary URL for system interaction is <code>https://enigma.lv/VoiceNoteSync/</code>. This URL forms the foundation for all GET request interactions with the system.</p>

            <h2>Using the System</h2>

            <p>IMPORTANT: Always open links in the browser, even if previously opened. Always append '&rnd=xxx' to each request to ensure unique request handling. Ensure that notes and texts are HTML encoded to maintain proper formatting and prevent breakage.</p>

            <h3>1. Generating GET Requests</h3>
            <p>ChatGPT can create notes based on user voice commands by forming GET requests. The structure for these requests is as follows:</p>
            <ul>
                <li>To create a note: <code>https://enigma.lv/VoiceNoteSync/?type=[noteType]&content=[content]&secretKey=[secretKey]</code></li>
                <li>To retrieve all notes: <code>https://enigma.lv/VoiceNoteSync/?type=getNotes&secretKey=[secretKey]</code> - This enables the checking of stored events via JSON using the secret key.</li>
            </ul>

            <h3>2. Handling Secret Keys</h3>
            <p>For users without a secret key, ChatGPT should send a request without one. The system will then generate a new key and return it in the response. ChatGPT must store this key securely for all subsequent requests.</p>

            <h3>3. Note Types and Parameters</h3>
            <ul>
                <li><strong>Text Note</strong>: Uses <code>type=text</code> with a <code>content</code> parameter for straightforward text notes.</li>
                <li><strong>Calendar Event</strong>: Uses <code>type=calendar</code> with <code>date</code>, <code>time</code>, and <code>content</code> parameters. The content should be a JSON-encoded array adhering to the ICS format. ChatGPT is responsible for generating detailed ICS data, which must then be JSON encoded. ChatGPT should endeavor to include or guess a location address for calendar events, aiding in navigation and applicability.</li>
                <li><strong>DALL·E Image Prompt</strong>: Uses <code>type=dalleImage</code> with a <code>prompt</code> parameter. Note: When asked to store an image generated by DALL·E, only the prompt used for generation is stored, ensuring no copyright issues.</li>
                <li><strong>Voice Note</strong>: Uses <code>type=voiceNote</code> with a <code>content</code> parameter for transcribed voice content.</li>
            </ul>

            <h3>Special Instructions for ChatGPT</h3>
            <ul>
                <li>For calendar notes, ChatGPT must generate comprehensive ICS data, including fields like SUMMARY, DESCRIPTION, DTSTART, DTEND, LOCATION, etc.</li>
                <li>For storing DALL·E image prompts, only the prompt used for generation is stored, aligning with copyright compliance.</li>
            </ul>

            <h3>4. Sending Requests via the Browser</h3>
            <p>ChatGPT should utilize its built-in browser capability to send GET requests. Constructed URLs must be opened in the browser tool.</p>

            <h3>5. Analyzing Responses</h3>
            <p>ChatGPT must carefully analyze the JSON response from the system to ascertain the outcome of each request. Responses include the status of the request, the type of note, and its content. ChatGPT should always verify the results to understand whether the note was successfully added or not.</p>

            <h3>6. Handling Errors and Feedback</h3>
            <p>If the response indicates an error, such as an invalid request type or missing parameters, ChatGPT should inform the user and request clarification. Additionally, if a new secret key is generated, ChatGPT should notify the user and provide the necessary links to view the notes.</p>

            <h3>7. User Interaction</h3>
            <p>ChatGPT should guide users through the note creation process, providing clear instructions and informing them about the ability to retrieve their notes using the secret key.</p>

            <h2>Example Usage</h2>
            <ul>
                <!-- Example for Text Note -->
                <li><strong>User</strong>: "Create a text note reminding me to call the dentist tomorrow."</li>
                <li><strong>ChatGPT</strong>: Forms the GET request URL for a text note: <code>https://enigma.lv/VoiceNoteSync/?type=text&content=Call the dentist tomorrow&secretKey=[User's Secret Key]</code>.</li>
                <li><strong>ChatGPT</strong>: Executes the request using the browser tool, analyzes the response, and confirms to the user: "Text note created to remind you to call the dentist tomorrow."</li>

                <!-- Example for Calendar Event -->
                <li><strong>User</strong>: "Schedule a meeting with the marketing team at the OpenAI office on Friday at 2 PM."</li>
                <li><strong>ChatGPT</strong>: Searches the internet for the address of the OpenAI office. Finds and uses the address "3180 18th St, San Francisco, CA 94110, United States" for the calendar event.</li>
                <li><strong>ChatGPT</strong>: Generates ICS data with the specific details and encodes it into JSON format. Forms the GET request URL for a calendar event: <code>https://enigma.lv/VoiceNoteSync/?type=calendar&content=[Encoded ICS JSON]&secretKey=[User's Secret Key]</code>.</li>
                <li><strong>ChatGPT</strong>: Sends the request and confirms to the user: "Meeting with the marketing team scheduled at the OpenAI office, 3180 18th St, San Francisco, CA, on Friday at 2 PM."</li>

                <!-- Example for DALL·E Image Prompt -->
                <li><strong>User</strong>: "Create a DALL·E prompt for an image of a futuristic cityscape at sunset."</li>
                <li><strong>ChatGPT</strong>: Constructs the GET request URL with the DALL·E prompt: <code>https://enigma.lv/VoiceNoteSync/?type=dalleImage&prompt=Futuristic cityscape at sunset&secretKey=[User's Secret Key]</code>.</li>
                <li><strong>ChatGPT</strong>: Executes the request, checks the response, and informs the user: "DALL·E image prompt for a futuristic cityscape at sunset has been saved."</li>
            </ul>

        </div>


        <div class="alert alert-success" role="alert">
            New secret key generated: <strong><?php echo htmlspecialchars($response['secretKey']); ?></strong><br>
            Save this key for future requests.
            <br>
            <a href="<?php echo htmlspecialchars($response['viewNotesLinkBrowser']); ?>">View your notes in browser</a>.
            <br>
            <a href="<?php echo htmlspecialchars($response['viewNotesLinkJSON']); ?>">View your notes in JSON format</a>.
            <br><br>
            <!-- Display example links for creating different types of notes -->
            <div>Example Links for Creating Notes:</div>
            <ul>
                <?php foreach ($response['examples'] as $exampleType => $exampleLink): ?>
                    <li><?php echo htmlspecialchars($exampleType); ?>: <a
                                href="<?php echo htmlspecialchars($exampleLink); ?>"
                                target="_blank"><?php echo htmlspecialchars($exampleLink); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div id="notes-container">
        <!-- PHP code to display notes -->
        <?php
        foreach ($formattedNotes as $note) {
            echo $note;
        }
        ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>

</body>
</html>

