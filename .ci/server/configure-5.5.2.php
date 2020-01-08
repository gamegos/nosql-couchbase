<?php
/**
 * Send an HTTP request.
 * @param  string $method
 * @param  string $url
 * @param  array $bodyData
 * @return string|bool
 */
function send_request($method, $url, array $bodyData = [])
{
    $context = stream_context_create([
        'http' => [
            'method'  => $method,
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($bodyData),
        ],
    ]);
    return file_get_contents($url, null, $context);
}

// Get the server name from the environment.
$host = getenv('COUCHBASE_SERVER_HOST');

// Wait for server initialization.
echo 'Waiting for server initialization...';
$startTime = time();
while (true) {
    $fp = @ fsockopen($host, 8091, $errno, $errstr);
    if (false !== $fp) {
        fclose($fp);
        echo "DONE\n";
        break;
    }
    if (time() - $startTime >= 60) {
        echo "\nCannot connect to the couchbase server. ({$errstr})\n";
        exit(1);
    }
    sleep(1);
    echo '.';
}

// Setup services.
echo "Setting up services...\n";
echo send_request(
    'POST',
    "http://{$host}:8091/node/controller/setupServices",
    ['services' => 'kv']
);
echo "\n";

// Setup credentials.
echo "Setting up credentials...\n";
echo send_request(
    'POST',
    "http://{$host}:8091/settings/web",
    [
        'username' => 'Administrator',
        'password' => 'password',
        'port'     => 'SAME',
    ]
);
echo "\n";

// Create the test bucket.
echo "Creating the test bucket...\n";
echo send_request(
    'POST',
    "http://Administrator:password@{$host}:8091/pools/default/buckets",
    [
        'name'          => 'test',
        'authType'      => 'none',
        'bucketType'    => 'couchbase',
        'ramQuotaMB'    => 100,
        'replicaIndex'  => 0,
        'replicaNumber' => 0,
        'flushEnabled'  => 1,
    ]
);
echo "\n";

// Create the test user.
echo "Creating the test user...\n";
echo send_request(
    'PUT',
    "http://Administrator:password@{$host}:8091/settings/rbac/users/local/test",
    [
        'roles'    => 'bucket_full_access[test]',
        'password' => 'testPass',
    ]
);
echo "\n";

// Wait for bucket initialization.
echo 'Waiting for bucket initialization...';
$startTime = time();
while (true) {
    $fp = @ fopen("http://test:testPass@{$host}:8091/pools/default/buckets/test", 'r');
    if (false !== $fp) {
        fclose($fp);
        echo "DONE\n";
        break;
    }
    if (time() - $startTime >= 60) {
        echo "\nCannot connect to the couchbase bucket. ({$http_response_header[0]})\n";
        exit(1);
    }
    sleep(1);
    echo '.';
}
