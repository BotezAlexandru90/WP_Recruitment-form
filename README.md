# EVE Online Recruitment System for WordPress

A powerful, self-contained WordPress plugin designed to streamline the recruitment and member management process for EVE Online corporations. This tool transforms a simple Google Sheet of applications into a dynamic, interactive dashboard within your WordPress admin area, complete with a visual application viewer, a member tracker, live filtering, and statistical charts.

---

### Key Features

*   **Google Sheet Integration:** Securely reads application data from a published Google Sheet CSV without ever modifying the source data.
*   **Dual-View Interface:**
    *   **Recruitment Viewer:** A unique UI that visually replicates your application form, providing a clear and organized way to review raw applications.
    *   **Member Tracker:** A powerful table-based interface to manage the entire lifecycle of an applicant after they've applied.
*   **Comprehensive Member Tracking:**
    *   Update applicant status (`Joined`, `Declined`, `Kicked`, etc.).
    *   Set and track join dates with a simple calendar.
    *   Assign and update member ranks (`Trial`, `Scout`).
    *   Keep private notes on each applicant.
*   **Live Dashboard & Analytics:**
    *   An interactive bar chart shows application trends over the last 12 months.
    *   A summary box provides at-a-glance counts of key metrics (Joined vs. Declined, Trial vs. Scout).
*   **Instant Filtering & Saving:**
    *   Live, client-side filters on the Member Tracker allow you to search and sort your applicants instantly without reloading the page.
    *   All changes in the tracker are saved automatically via AJAX in the background.
*   **Deep Customization:**
    *   A dedicated settings page to control every aspect of the plugin.
    *   Set custom colors for each recruitment status for at-a-glance visual identification.
    *   Map every field from your Google Sheet to the correct box in the custom viewer UI.
    *   Adjust the size and scale of the recruitment viewer to fit your screen.
*   **Secure & Role-Based:**
    *   Visible only to users with 'Administrator' or 'Editor' roles.
    *   Settings page is restricted to Administrators only.
*   **Self-Contained & Simple:**
    *   The entire plugin, including all CSS and JavaScript, is contained within a single PHP file for incredible simplicity and portability.

### Screenshots

_**Note:** Replace these placeholders with actual screenshots of your plugin in action!_

**The Member Tracker Dashboard:**
*A powerful overview with live filters, a monthly application chart, and a summary of key stats.*
<img width="1734" height="822" alt="image" src="https://github.com/user-attachments/assets/87e5957e-8a62-4fb2-bb9a-9008c9886766" />


**The Recruitment Viewer:**
*A unique UI that visually reconstructs the application form for easy reading.*
<img width="824" height="867" alt="image" src="https://github.com/user-attachments/assets/a069e686-630a-4338-9da7-02702502c440" />


**The Settings Page:**
*Total control over data sources, UI appearance, field mapping, and status colors.*
<img width="775" height="821" alt="image" src="https://github.com/user-attachments/assets/731c0048-4170-4fc1-89bb-2fc750ee1ca6" />



### Installation

#### Option 1: WordPress Admin Upload

1.  Download the `eve-recruitment-system.php` file from this repository.
2.  In your WordPress admin dashboard, navigate to `Plugins` -> `Add New`.
3.  Click the `Upload Plugin` button at the top.
4.  Click `Choose File` and select the `eve-recruitment-system.php` file you downloaded.
5.  Click `Install Now` and then `Activate Plugin`.

#### Option 2: Manual Upload

1.  Download the `eve-recruitment-system.php` file from this repository.
2.  Using an FTP client or your web host's file manager, upload the file to your WordPress installation's `wp-content/plugins/` directory.
3.  In your WordPress admin dashboard, navigate to `Plugins` -> `Installed Plugins`.
4.  Find "EVE Online Recruitment System" in the list and click `Activate`.


### How It Works & Usage

Once activated, a new menu item named **"EVE Recruitment"** will appear in your WordPress admin sidebar.

#### 1. Initial Setup (Settings)

Before you begin, an Administrator must configure the plugin:

1.  Navigate to `EVE Recruitment` -> `Settings`.
2.  **Data Source Settings:** Paste the public CSV link from your Google Sheet (`File` -> `Share` -> `Publish to web` -> `Comma-separated values (.csv)`).
3.  **Recruitment Viewer Field Mapping:** This is the most important step. For each field listed (e.g., "Player name", "Discord ID"), enter the corresponding **column number** from your Google Sheet. For example, if the player's name is in the second column of your sheet, you would enter `2`.
4.  **Status Color Settings:** Customize the background color for each recruitment status in the Member Tracker.
5.  Click **"Save All Settings"**.

#### 2. Recruitment Viewer

*   **Purpose:** To review new applications exactly as they were submitted.
*   **How to Use:** Select an applicant from the dropdown menu at the top. Their application will instantly appear below, formatted to look like the original form for easy reading.

#### 3. Member Tracker

*   **Purpose:** To manage the lifecycle of your applicants and members.
*   **How to Use:**
    *   **Dashboard:** Immediately see the application trend chart and the summary of your current roster.
    *   **Filtering:** Use the input boxes and dropdowns at the top of each column to instantly filter the table. Click the "Clear" button to reset the view.
    *   **Editing Data:** Simply click on any dropdown (`Recruitment Status`, `Rank`), select a new value, and it will be saved automatically. Click on the `Join Date` field to open a calendar, or type directly in the `Notes` field.

### Technical Details

*   **Backend:** PHP 8+
*   **Frontend:** Vanilla JavaScript (with jQuery for AJAX and UI elements)
*   **Charting:** Chart.js
*   **Architecture:** The entire plugin is self-contained in a single PHP file, with CSS and JavaScript embedded and loaded dynamically via standard WordPress hooks. All tracker data is stored securely in the WordPress `wp_options` table.

---
