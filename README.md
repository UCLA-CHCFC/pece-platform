# PECE Platform — Custom Code Package

Code repository for the Pomona Early Childhood Ecosystems Transformation Accelerator platform.

## Directory Structure

```
pece-platform/
├── mu-plugins/                    # Must-use plugins (auto-loaded by WordPress)
│   ├── pece-ics-generator.php     # .ics calendar invite on RSVP submission
│   └── pece-calendar-links.php    # [pece_calendar_links] shortcode
├── theme/                         # Child theme
│   ├── style.css                  # Child theme stylesheet + brand variables
│   └── functions.php              # Theme functions, registration hooks
├── scripts/
│   └── deploy.sh                  # One-command deploy script
├── config/
│   └── nginx-site.conf            # Nginx configuration template
└── README.md                      # This file
```

## Setup

### 1. Clone to the VM

```bash
cd /var/www/html/wordpress/wp-content
sudo git clone git@github.com:YOUR_ORG/pece-platform.git pece-deploy
```

### 2. Create Symlinks

```bash
# Child theme
sudo ln -s /var/www/html/wordpress/wp-content/pece-deploy/theme /var/www/html/wordpress/wp-content/themes/pece-child

# Must-use plugins
sudo ln -s /var/www/html/wordpress/wp-content/pece-deploy/mu-plugins/pece-ics-generator.php /var/www/html/wordpress/wp-content/mu-plugins/
sudo ln -s /var/www/html/wordpress/wp-content/pece-deploy/mu-plugins/pece-calendar-links.php /var/www/html/wordpress/wp-content/mu-plugins/
```

### 3. Configure

- **pece-ics-generator.php**: Update `PECE_RSVP_FORM_ID` and field mapping constants to match your Forminator form.
- **theme/style.css**: Change the `Template` line to match your parent theme's folder name.
- **config/nginx-site.conf**: Replace `YOUR_VM_IP` with your VM's static IP.

### 4. Deploy

```bash
sudo bash /var/www/html/wordpress/wp-content/pece-deploy/scripts/deploy.sh
```

## Shortcode Usage

### [pece_calendar_links]

Add to any page or Forminator confirmation message:

```
[pece_calendar_links title="Community Meeting" date="2026-04-15" start="09:00" end="10:30" location="Pomona Community Center" description="Monthly partner meeting"]
```

Renders Google Calendar, Outlook, and Apple/Download .ics buttons.

## Important Notes

- **mu-plugins load automatically** — no activation needed in WordPress admin.
- **Never commit** the GCP service account JSON key to this repository.
- **Test .ics files** with Gmail, Outlook, and Apple Calendar before going live.
- Run `deploy.sh` after every `git push` to update the live site.
