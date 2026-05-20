<?php
// Copy this file to includes/mongodb_config.php and fill it with your private values.
// Keep mongodb_config.php private because it contains credentials.

// On Render, prefer environment variables instead of this file:
// MONGODB_URI, MONGODB_DATABASE, MONGODB_COLLECTION.

// Option 1: direct MongoDB driver, used by the Dockerfile for Render.
define('MONGODB_URI', 'mongodb+srv://<user>:<password>@<cluster>.mongodb.net/?retryWrites=true&w=majority');

// Option 2: MongoDB HTTP bridge compatible with the Data API payloads.
// Useful on shared hosting when the PHP mongodb extension is not available.
define('MONGODB_HTTP_API_URL', 'https://<your-mongodb-http-api>/action');
define('MONGODB_HTTP_API_KEY', '<api-key>');
define('MONGODB_API_KEY_HEADER', 'api-key');

define('MONGODB_DATA_SOURCE', 'Cluster0');
define('MONGODB_DATABASE', 'vite_et_gourmand');
define('MONGODB_COLLECTION', 'statistiques_menus');
