# Limmud WebApp Generator

Generator for Limmud FSU Israel festival Web App.

## Installation

## Step 1: Copy files

- Copy all files to /webapp folder on your server

### Step 2: Create application credentials

- Open the [Credentials page](https://console.developers.google.com/apis/credentials) in the API Console.
- Click **Create credentials > OAuth client ID**.
  - Set the application type to "Web application"
  - For name enter "Limmud WebApp Generator"
  - For authorized redirect URIs enter "https://<hostname>/webapp/redirect.php"  
  - Click **Create**.
  - Click **Download JSON***
- Copy downloaded file to data/credentials.json

### Step 3: Authorize generator to access Google Sheets

- Open https://<hostname>/webapp/get_token.php
- Click link to start authorization process
- Log in with credentials of user who owns Google Sheet that holds the schedule
- Complete authorization process (ignore the warning)
- If all is good you will see 'Token successfully created'     

## Usage

### Configuration

- Set Google Sheet ID and the application name
- If you don't want users to accidentally change configuration create 'lock.txt' file

### Generate

- Click **Copy Assets** to copy 'app skeleton' - this is typically needed only once
- Click **Generate** to generate application from the data in Google Sheets
- Click **Open WebApp** to view generated application

### Photos

- Upload photos via **Upload** button. 
- Photos should be in JPEG format and sized 300x300 (if size is different photo will be resampled on upload)
- Use **Show** to view all uploaded photos.
