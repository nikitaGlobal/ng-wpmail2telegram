=== NG-Lazyload ===
Contributors: nikitaglobal
Plugin Name: NG-WpMail2Telegram
Tags: telegram, wp-mail, wpmail, mail, notifications, messengers
Author: Nikita Menshutin
Requires at least: 3.6
Tested up to: 5.1
Stable tag: 1.4
Requires PHP: 5.6
Version: 1.0
License: 			GPLv2 or later
License URI: 		http://www.gnu.org/licenses/gpl-2.0.html

Sends all mails to telegram user via your own bot.
No need in many other plugins as you can use any email notifications which will be redirected to telegram

Developed by Nikita Menshutin

[https://nikita.global/](https://nikita.global)

** Set up **
1. Register your own bot as described [https://core.telegram.org/bots#3-how-do-i-create-a-bot](here)
2. Fill in plugin settings with your bot token
3. Each user which has access to wp-admin should start chat with your bot and follow the subscription link

Your website should support https.

** Message Filters **
Currently can send mails as plaintext, files, subjects only and truncated message.
You can extend this range using the filters - see source code.

This plugin makes HTTP-requests to 3rd party service [Telegram API](https://core.telegram.org/api)
to send notifications.

== Description ==

Create your own notification bot which will send emails to your dashboard users via telegram.

== Installation ==

Use WordPress' Add New Plugin feature, searching "NG-mail2Telegram, or download the archive and:

1. Unzip the archive on your computer  
2. Upload plugin directory to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Register your own bot as described [https://core.telegram.org/bots#6-botfather](here)
5. Fill in plugin settings with your bot token

== Changelog ==


= 1.0 (2019-05-16) =
* The First Upload, but was tested before at several sites
