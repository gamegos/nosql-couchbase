<?php
namespace Gamegos\NoSql\Tests\Storage;

/* Import from gamegos/nosql */
use Gamegos\NoSql\Storage\Couchbase;

/**
 * Test Class for Storage\Couchbase
 * @author Safak Ozpinar <safak@gamegos.com>
 */
class CouchbaseTest extends AbstractCommonStorageTest
{
    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass()
    {
        // Forward error logs to /dev/null for Couchbase extension errors. We use exceptions.
        ini_set('error_log', '/dev/null');
        // Clear the test bucket before start.
        self::clearTestBucket();
    }

    /**
     * {@inheritdoc}
     */
    public static function tearDownAfterClass()
    {
        // Restore 'error_log' ini setting.
        ini_restore('error_log');
    }

    /**
     * Clear the test bucket.
     */
    protected static function clearTestBucket()
    {
        $url = strtr(
            'http://{username}:{password}@{hostname}:8091/pools/default/buckets/{bucket}/controller/doFlush',
            [
                '{hostname}' => getenv('COUCHBASE_SERVER_HOST'),
                '{bucket}'   => 'test',
                '{username}' => 'Administrator',
                '{password}' => 'password',
            ]
        );
        file_get_contents($url, null, stream_context_create(['http' => ['method' => 'POST']]));
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        self::clearTestBucket();
    }

    /**
     * {@inheritdoc}
     */
    public function createStorage()
    {
        $params = [
            'dsn'      => 'couchbase://' . getenv('COUCHBASE_SERVER_HOST'),
            'bucket'   => 'test',
        ];
        if (version_compare(getenv('COUCHBASE_SERVER_VERSION'), '5.0.0', 'ge')) {
            $params['username'] = 'test';
            $params['password'] = 'testPass';
        }
        return new Couchbase($params);
    }

    /**
     * {@inheritdoc}
     */
    public function nonStringProvider()
    {
        $data = parent::nonStringProvider();
        // Values type of 'resource' cause generic error in Couchbase SDK.
        unset($data['resource']);
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function nonIntegerProvider()
    {
        $data = parent::nonIntegerProvider();
        // Values type of 'resource' cause generic error in Couchbase SDK.
        unset($data['resource']);
        return $data;
    }
}
