This is a plugin that makes Moodle an Identity Provider site: other application can use Moodle as a login portal

Requires SimpleSAMLphp, configured as IdP: https://simplesamlphp.org/docs/stable/simplesamlphp-idp

ATTENTION: SimpleSAMLphp's session store (config/config.php, 'store.type') MUST BE "sql". "phpsession" will not work, "memcache" is not tested
ATTENTION: SimpleSAMLphp's baseurlpath (config/config.php, 'baseurlpath') MUST BE in the full URL format

To make the plugin work:
1: use standard Moodle instalation mechanism;
2: enable and configure the plugin via Moodle (Site administration -> Plugins -> Authentication). "Auth source" is the name from step 4, here it's "moodle-userpass"
3: in SimplesamlPHP, add the plugin's ./moodle directory to SimpleSAMLphp' /modules directory. Since these must be on the same physical server, a symlink works perfectly
4: in SimpleSAMLphp config/authsources.php, add following to $config:
    'moodle-userpass' => array(
        'moodle:External',
        'moodle_coderoot' => '/var/www/ticket/moodle314/www',
        'logout_url' => 'https://10.0.0.28/moodle314/auth/samlidp/logout.php',  // plugin's logout page
        'login_url' => 'https://10.0.0.28/moodle314/login/index.php',           // standard Moodle login page
        'cookie_name' => 'MoodleSAMLIDPSessionID',
    ),
5: in SimpleSAMLphp, in metadata/saml20-idp-hosted.php, modify 'auth' with the name from step 4:
    'auth' => 'moodle-userpass',

KNOWN ISSUES
1. If a user logs out from Moodle, it will not log them out from their SP application. The logout process is one-directional, from the SP app to Moodle
