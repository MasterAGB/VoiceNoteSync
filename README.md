# Enigma - VoiceNote Sync: User Guide

## Table of Contents

- [Introduction](#introduction)
- [Getting Started](#getting-started)
- [Note Types and Usage](#note-types-and-usage)
- [Registration and Secret Key](#registration-and-secret-key)
- [API Usage and Examples](#api-usage-and-examples)
- [Response Formats](#response-formats)
- [Integration with ChatGPT](#integration-with-chatgpt)
- [Special Instructions for ChatGPT](#special-instructions-for-chatgpt)

## Introduction

"Enigma - VoiceNote Sync" is an advanced note-taking system, expertly designed to integrate with ChatGPT, particularly
focusing on voice-based interactions. This guide aims to provide comprehensive instructions on utilizing the service for
a variety of note types, enhancing the user experience with ChatGPT.

## Getting Started

To begin using "Enigma - VoiceNote Sync," initiate a note creation command through ChatGPT. The service is versatile,
supporting a range of note types, each with its unique format and purpose.

## Note Types and Usage

- **Text Note**: Simple, straightforward text-based notes for quick information storage.
- **Calendar Event**: Efficiently store calendar events in ICS format, allowing for seamless integration with calendar
  applications. ChatGPT will assist in generating the necessary ICS data, which will be then encoded in JSON.
- **DALL·E Image Prompt**: Preserve prompts for DALL·E generated images, facilitating creative endeavors.
- **Voice Note**: Easily convert and store voice input into transcribed text.

## Registration and Secret Key

New note creation requests initiate a registration process if a secret key isn't already associated with the user. This
secret key, essential for future requests, will be generated and provided in the JSON response.

## API Usage and Examples with Secret Key

Detailed examples of API usage with corresponding response samples:

1. **Creating a Text Note**
    - **Request**: `/createNote?type=text&content=YourNoteContent&secretKey=YourSecretKey`
    - **Response Sample**:
      ```json
      {
        "status": "success",
        "noteType": "text",
        "content": "YourNoteContent",
        "secretKey": "YourSecretKey"
      }
      ```
2. **Adding a Calendar Event**
    - **Request**: `/createNote?type=calendar&content=JSONEncodedICSData&secretKey=YourSecretKey`
    - **Response Sample**:
      ```json
      {
        "status": "success",
        "noteType": "calendar",
        "content": "JSONEncodedICSData",
        "secretKey": "YourSecretKey"
      }
      ```
3. **Saving a DALL·E Image Prompt**
    - **Request**: `/createNote?type=dalleImage&content=ImagePrompt&secretKey=YourSecretKey`
    - **Response Sample**:
      ```json
      {
        "status": "success",
        "noteType": "dalleImage",
        "content": "ImagePrompt",
        "secretKey": "YourSecretKey"
      }
      ```
4. **Creating a Voice Note**
    - **Request**: `/createNote?type=voiceNote&content=TranscribedVoiceContent&secretKey=YourSecretKey`
    - **Response Sample**:
      ```json
      {
        "status": "success",
        "noteType": "voiceNote",
        "content": "TranscribedVoiceContent",
        "secretKey": "YourSecretKey"
      }
      ```

## Response Formats

The service responds in JSON format, providing clear status, note type, content, and secret key for authentication and
user verification.

## Integration with ChatGPT

When interacting with ChatGPT for voice conversations, clearly specify the desired note type. ChatGPT utilizes the API
to create notes based on your voice commands, efficiently handling the registration process and note creation.

## Special Instructions for ChatGPT

- **GET Request Method**: ChatGPT should use the GET request method for all interactions with the "Enigma - VoiceNote
  Sync" API. This approach ensures a streamlined process for creating and retrieving notes.

- **Response Analysis**: After each API interaction, ChatGPT must analyze the JSON response. This analysis is critical
  to confirm the successful creation or retrieval of notes. The response will contain information such as the status of
  the request, the type of note created, and any relevant content.

- **Request Structure**:
    - For creating notes: Use `/createNote?type=[noteType]&content=[content]&secretKey=[secretKey]`. This structure
      allows ChatGPT to specify the type of note to be created, the content of the note, and the user's secret key for
      authentication.
    - For retrieving notes: Use `/getNotes?secretKey=[secretKey]`. This request retrieves all notes associated with the
      provided secret key.

- **Note Types and Parameters**:
    - The system supports various note types, including text, calendar, dalleImage, and voiceNote. Each note type
      requires specific parameters in the API request.

- **Calendar Events**:
    - For calendar events, ChatGPT should generate detailed ICS data, incorporating essential fields like SUMMARY,
      DESCRIPTION, DTSTART, DTEND, and LOCATION. Accuracy in this data is crucial for the utility of the calendar event.
    - The ICS data should be JSON encoded and included in the content parameter of the API request.
    - Additionally, for events with specific locations, ChatGPT should attempt to find and include accurate address
      information, enhancing the event's practicality and navigational use.

- **DALL·E Image Prompts**:
    - When storing images generated by DALL·E, ChatGPT should save only the prompt used for the image generation, not
      the base64 encoded image data. This approach aligns with copyright compliance and respects creative integrity.
    - The prompt should be specified in the GET request, allowing the system to record and retrieve the creative input
      behind the DALL·E generated image.
