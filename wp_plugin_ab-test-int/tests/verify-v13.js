const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');

function read(relativePath) {
    return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

function contains(source, pattern, message) {
    assert.match(source, pattern, message);
}

const plugin = read('ab-test-int.php');
const database = read('includes/class-abti-database.php');
const rest = read('includes/class-abti-rest.php');
const frontend = read('includes/class-abti-frontend.php');
const tracker = read('public/js/abti-frontend.js');
const admin = read('includes/class-abti-admin.php');

contains(plugin, /Version:\s*1\.3\.0/, 'Plugin header must be 1.3.0');
contains(plugin, /define\(\s*'ABTI_VERSION',\s*'1\.3\.0'\s*\)/, 'ABTI_VERSION must be 1.3.0');
contains(plugin, /ABTI_Database::maybe_upgrade\(\)/, 'Upload replacement must run the DB upgrade');

contains(database, /abti_assignments/, 'Assignments table must be additive');
contains(database, /UNIQUE KEY\s+test_visitor\s+\(test_id,\s*visitor_id\)/, 'Visitor assignment must be unique per test');
contains(database, /GET_LOCK/, 'Quota assignment must use a test-scoped MySQL lock');
contains(database, /RELEASE_LOCK/, 'Quota assignment lock must be released');
contains(database, /ABTI_Quota::choose_variation/, 'Database assignment must use the tested quota selector');

contains(rest, /'\/assign'/, 'REST assign route must exist');
contains(rest, /no-store/, 'Assignment responses must prohibit caching');

contains(frontend, /abti_v3_test_/, 'Picker assignments must use v3 storage keys');
contains(frontend, /abti_v3_visitor/, 'Picker visitor ID must use a v3 storage key');
contains(frontend, /xhr\.open\('POST',c\.assign,false\)/, 'First assignment must block parsing until the server responds');
contains(frontend, /data-no-optimize="1"/, 'Inline picker must remain excluded from optimization');
contains(frontend, /rocket_rucss_inline_content_exclusions/, 'WP Rocket Used CSS exclusion must remain active');

contains(tracker, /abti_v3_/, 'Tracking storage keys must be versioned');
assert.doesNotMatch(tracker, /STORAGE_PREFIX\s*=\s*'abti_'/, 'Legacy storage prefix must not be reused');

contains(admin, /abti-help/, 'Admin help submenu must exist');
assert.ok(fs.existsSync(path.join(root, 'admin/views/help.php')), 'Help view must exist');

console.log('v1.3 static contract: PASS');
