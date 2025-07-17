# Bunny.net for Craft CMS

This [Craft CMS](https://craftcms.com/) plugin seamlessly integrates with [Bunny.net](https://bunny.net/), providing two powerful filesystem drivers:

- Bunny Storage Filesystem – Upload assets directly from Craft’s control panel to your Bunny Storage Zone.
- Bunny CDN Filesystem – Offload image transformations to Bunny's Dynamic Image API, reducing load on your Craft CMS server.

Ideal for boosting performance and streamlining media delivery, this plugin makes it easy to manage and optimize your assets using Bunny.net’s infrastructure.

## Requirements

This plugin requires Craft CMS 5.8.0 or later, and PHP 8.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Bunny.net”. Then press “Install”.

### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require thomasvantuycom/craft-bunny

# tell Craft to install the plugin
./craft plugin/install bunny
```
## Setup

### Bunny Storage Filesystem

To configure the Bunny Storage filesystem, you’ll need the following:

- Zone Name – The name of your Bunny.net storage zone.
- Primary Region – The main region where your storage zone is hosted.
- Access Key – This is not your general Bunny.net API key. Instead, it’s the password found under the "FTP & API Access" tab in your storage zone’s settings.

If you want assets in this filesystem to have publicly accessible URLs, you’ll also need to:

1. Create a Pull Zone in your Bunny.net dashboard and link it to your Storage Zone.
2. Provide the Pull Zone URL in the filesystem’s configuration under the appropriate setting.

### Bunny CDN Filesystem

The Bunny CDN filesystem is intended only for use as the Transform Filesystem of a volume. Its sole purpose is to offload image transformations to Bunny.net using their Dynamic Image API.

To use this filesystem:

1. Create a Bunny Pull Zone and link it to either:
    - A Bunny Storage Zone (as described above), or
    - Any other Craft CMS filesystem that provides publicly accessible asset URLs.
2. In your Pull Zone settings on Bunny.net:
    - Enable the Optimizer feature.
    - Specifically enable the Dynamic Image API.

This setup allows Craft to generate transformed images via Bunny’s edge network, significantly improving performance and reducing processing load on your server.

### Image Transformations

You can use Craft's standard image transformation syntax—no changes to your templates are needed. This plugin automatically maps Craft’s transformation parameters to Bunny.net’s Dynamic Image API behind the scenes.

Bunny currently supports the following Craft transformation modes:
- crop
- fit

Other modes are not supported and will be ignored.
