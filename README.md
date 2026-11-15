Module for webtrees genealogy software to show XREF and UID values
==================================================================

[![Latest Release](https://img.shields.io/github/release/elysch/webtrees-mitalteli-show-xref.svg)][1]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.0.x-green)][2]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.1.x-green)][2]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)][2]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.3.x%20(dev)-yellow)][2]
[![Downloads](https://img.shields.io/github/downloads/elysch/webtrees-mitalteli-show-xref/total.svg)]()
![image](https://img.shields.io/github/downloads/elysch/webtrees-mitalteli-show-xref/latest/total)

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/donate/?business=EU37HN97QD9EU&no_recurring=0&currency_code=MXN)

Description
------------
This module adds a sidebar to each individual's view that shows the XREF and UID (identification strings) for him/her, all of his/her families, and all family members.

You can add a reference to other record within a text or note field writing something like "@&lt;XREF&gt;@" (where &lt;XREF&gt; should be substituted by an existent identification string). I hope there is a way to include references using UID identification strings soon.

**It's important to note that the XREF's can be changed, so the Webtrees user should use them at his/her own risk.**

NOTE 1: When adding reference links from one record to another, I recommend to always include arbitrary text that permits to identify what information is being referred to, just in case the identification string gets removed/replaced in the destination and the link stops working.

NOTE 2: You can hide the UID values in the module's configuration in webtree's Control panel.

NOTE 3: You can Enable/Disable WebTrees Gender background Colours for this module in the module's configuration in webtree's Control panel. (the actual colours are set in WebTrees CSS and or Themes CSS NOT in this module)

Installation & upgrading
------------------------
Unpack the zip file and place the mitalteli-show-xref folder in the modules_v4 folder of webtrees. Upload the newly added folder to your server. It is activated by default and will work immediately. No additional configuration is required.

Link symbols font
-----------------
The module bundles a small font (`resources/fonts/`, subsets of GNU Unifont under the SIL OFL 1.1, about 31 KB, a single file) so the link symbols (for example ⮺ and 🡆) are displayed also on devices whose system fonts do not have them, like most Android phones. It is served by the module itself, nobody has to install or configure anything, and the browser only downloads it when a symbol that the device cannot draw is shown. See `resources/fonts/NOTICE.txt` and `resources/fonts/OFL.txt` for the license and how to replace it.

Translation
-----------
This module contains a few translatable textstrings. Copy the file es.php in the resources/lang folder and replace the Spanish text with the translation into your own language. Use the official two-letter language code as file name. Look in the webtrees folder resources/lang to find the correct code.

It would be great if you could share to the community the translated file by [creating a new issue on GitHub][3].

Bugs & feature requests
-------------------------
You can [create a new issue on GitHub][3] or comment in the [webtrees forum][4]

Please search in those places before writing new messages. Maybe your concern has already been discussed.

 [1]: https://github.com/elysch/webtrees-mitalteli-show-xref/releases/latest
 [2]: https://webtrees.github.io/download
 [3]: https://github.com/elysch/webtrees-mitalteli-show-xref/issues?state=open
 [4]: https://www.webtrees.net/index.php/forum/index
