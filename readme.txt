=== NG-Mail2Telegram ===
Contributors: nikitaglobal
Plugin Name: NG-WpMail2Telegram
Tags: telegram, wp-mail, wpmail, mail, notifications, messengers
Author: Nikita Menshutin
Text Domain: ng-mail2telegram
Domain Path: /languages
Requires at least: 3.6
Tested up to: 5.3.2
Stable tag: 1.3
Requires PHP: 5.6
Version: 1.3
License: 			GPLv2 or later
License URI: 		http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Create your own notification bot which will send emails to your dashboard users via telegram.

Sends all mails to telegram user via your own bot.
No need in many other plugins as you can use any email notifications which will be redirected to telegram

[https://nikita.global/](https://nikita.global)

Your website should support https.

** Message Filters **
Currently can send mails as plaintext, files, subjects only and truncated message.
You can extend this range using the filters - see source code.

This plugin makes HTTP-requests to 3rd party service [Telegram API](https://core.telegram.org/api)
to send notifications.

== Installation ==

Use WordPress' Add New Plugin feature, searching "NG-mail2Telegram, or download the archive and:

1. Unzip the archive on your computer  
2. Upload plugin directory to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Register your own bot as described [https://core.telegram.org/bots#6-botfather](here)
5. Fill in plugin settings with your bot token
6. Each user which has access to wp-admin should start chat with your bot and follow the subscription link

== Changelog ==
= 1.4 (2020-01-25)
* Assets updated
* Checked compatibility up to 5.3.2

= 1.3 (2019-05-20) =
* Translation strings are more evident for parsers
* Bugfix

= 1.2 (2019-05-20) =
* Better multilanguage support (try)

= 1.1 (2019-05-19) =
* Better multilanguage support

= 1.0 (2019-05-16) =
* The First Upload, but was tested before at several sites
