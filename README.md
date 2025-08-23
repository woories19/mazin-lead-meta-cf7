# Mazin Lead Meta for Contact Form 7

A WordPress plugin that automatically captures user metadata (IP, location, browser, OS, device) when they submit Contact Form 7 forms.

## Features

- **IP Address Detection**: Automatically detects user's real IP address
- **Geolocation**: Resolves IP to country and city using free APIs
- **Device Detection**: Identifies browser, operating system, and device type
- **Timezone**: Captures user's timezone
- **Multiple IP Services**: Supports ipapi.co, ipwho.is, and ipinfo.io
- **Admin Settings**: Configurable through WordPress admin panel

## Installation

1. Upload the plugin files to `/wp-content/plugins/mazin-lead-meta-cf7/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Mazin CF7 Lead Meta to configure options

## Usage

### In Contact Form 7 Email Templates

Add these tags to your Contact Form 7 email templates:

```
New contact form submission:

Name: [your-name]
Email: [your-email]
Message: [your-message]

User Details:
IP Address: [_mazin_user_ip]
Location: [_mazin_country_city]
Timezone: [_mazin_timezone]
Browser: [_mazin_browser]
Operating System: [_mazin_os]
Device Type: [_mazin_device]
```

### Available Meta Tags

- `[_mazin_user_ip]` - User's IP address
- `[_mazin_country_city]` - Country and city (e.g., "United States, New York")
- `[_mazin_timezone]` - User's timezone
- `[_mazin_browser]` - Browser type (Chrome, Firefox, Safari, etc.)
- `[_mazin_os]` - Operating system (Windows, MacOS, Linux, Android, iOS)
- `[_mazin_device]` - Device type (Desktop, Mobile, Tablet)

## Testing

To test if the plugin is working:

1. Make sure you're logged in as an administrator
2. Visit your site with `?mazin_test_meta=1` added to the URL
3. You should see a test page showing all the metadata

Example: `https://yoursite.com/?mazin_test_meta=1`

## Configuration

### IP Lookup Service
Choose from three free IP geolocation services:
- **ipapi.co** (default) - Fast, reliable, good for production
- **ipwho.is** - Alternative service with good accuracy
- **ipinfo.io** - Another reliable option

### Debug Logging
Enable logging to troubleshoot issues. Logs are written to `wp-content/mazin-cf7-meta.log`

## Troubleshooting

### Plugin Not Working?
1. Check if Contact Form 7 is installed and activated
2. Verify the plugin is activated
3. Check your WordPress debug log for errors
4. Test with `?mazin_test_meta=1` to see if metadata is being collected

### IP Address Shows as "Unknown"?
1. Your server might be behind a proxy/CDN
2. Check if you're using Cloudflare, AWS, or similar services
3. The plugin automatically detects most proxy configurations

### Location Shows as "Unknown"?
1. Check if the selected IP service is working
2. Try switching to a different IP service in settings
3. Some IP addresses (especially private ones) can't be geolocated

## Requirements

- WordPress 5.0+
- Contact Form 7 plugin
- PHP 7.4+
- cURL support (for IP lookups)

## Support

For support, please visit: https://mazindigital.com

## Changelog

### Version 0.4.0
- Fixed broken settings page
- Implemented multiple IP lookup services
- Added proper error handling and logging
- Improved IP detection for proxy/CDN setups
- Added test functionality for debugging