#Salesforce Encrypt
Salesforce Encrypt relies on [Encrypt](http://drupal.org/project/encrypt) to 
obfuscate sensitive stateful information stored by Salesforce module: OAuth 
Access and Refresh Tokens, as well as Salesforce Identity.

# Dependencies
- http://drupal.org/project/encrypt
- http://drupal.org/project/key
- https://www.drupal.org/project/real_aes (or encryption method provider of your 
  choice)

# Install
- Enable Encrypt, Key, and an encryption method provider like Real AES
- Create a Key and Encryption Profile according to instructions in those modules
- Enable Salesforce Encrypt module
- Assign the encryption profile to be used at admin/config/salesforce/encrypt
- Assign "administer salesforce encryption" permission to any roles who need it

That's it - your tokens and identity are now encrypted.

# Note
As long as this module is enabled and encryption is not enabled, you'll get a 
runtime requirement error on admin/reports/status.
