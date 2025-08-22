# Mazin Lead Meta for CF7

A lightweight WordPress plugin that extends **Contact Form 7** by automatically capturing and attaching user metadata to form submissions.  

Collected data includes:  
- User IP address  
- Country, city, and timezone (via IP lookup)  
- Device type (mobile/tablet/desktop)  
- Operating system  
- Browser details  

All captured metadata can be inserted into CF7 email templates via shortcodes, or auto-appended to emails based on settings.  

## Features

- ✅ IP & Geolocation (country, city, timezone)  
- ✅ User Agent parsing (browser, OS, device type)  
- ✅ Custom CF7 mail-tags (e.g., `_mazin_user_ip`, `_mazin_country_city`, `_mazin_os`)  
- ✅ Admin settings page to enable/disable features, configure API provider & keys  
- ✅ Option to auto-append metadata block to all outgoing CF7 emails  
- ✅ Lightweight GitHub Updater integration for easy plugin updates  

## Installation

1. Download or clone this repo:  
   git clone https://github.com/woories19/mazin-lead-meta-cf7.git

2. Zip the plugin folder:
    cd mazin-lead-meta-cf7
    zip -r mazin-lead-meta-cf7.zip .

3. In WordPress admin → Plugins → Add New → Upload Plugin → choose mazin-lead-meta-cf7.zip.

4. Activate the plugin.

## Usage
Contact Form 7 Mail Tags

Use any of the following mail-tags inside your CF7 Mail template:
[_mazin_user_ip]
[_mazin_country]
[_mazin_city]
[_mazin_country_city]
[_mazin_timezone]
[_mazin_user_agent]
[_mazin_browser]
[_mazin_os]
[_mazin_device]

## Auto-Append

Enable “Auto-append metadata” in plugin settings to automatically add a metadata block at the bottom of every CF7 email.

## Settings

Navigate to Settings → Mazin Lead Meta (CF7) to configure:

- Enable/disable metadata fields
- Choose API provider (ipapi, ipinfo, ipstack)
- Add API key if required
- Control timeout & fallback
- Toggle auto-append option

## GitHub Updater

This plugin uses a lightweight GitHub Updater class.

To enable auto-updates:

- Ensure the plugin is installed from this GitHub repo.
- The updater will check for new releases in the main branch.

## Requirements

- WordPress 6.0+
- Contact Form 7 plugin installed and active
- PHP 7.4+

## Development

Pull requests and issues are welcome!

1. Fork the repo

2. Create a feature branch (feature/awesome-feature)

3. Commit and push your changes

4. Submit a PR

## License

GPL-2.0-or-later.
Free to use, modify, and distribute.

## Credits

Developed by Mazin Digital for internal use and client projects.