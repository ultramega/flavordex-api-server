# Flavordex API Server

This is the backend server that the Flavordex app uses to sync journal data.

   * [Official Website](http://flavordex.com/)
   * [Flavordex for Android on GitHub](https://github.com/ultramega/flavordex)

## Requirements

   * PHP 5.5
   * MySQL 5.6
   * PHP-JWT [[link](https://github.com/firebase/php-jwt)]
   * A Firebase project for Cloud Messaging [[link](https://firebase.google.com/)]

## Setup

   * Rename *Config.php.sample* to *Config.php* (located in *web/Flavordex*).
   * Edit *Config.php* with your configuration parameters.
   * Place the contents of the *web/* directory in a Web accessible location.

## License

The source code for the Flavordex API Server is released under the terms of the
[MIT License](http://sguidetti.mit-license.org/).
