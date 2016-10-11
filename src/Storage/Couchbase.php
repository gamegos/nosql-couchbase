<?php
namespace Gamegos\NoSql\Storage;

/* Imports from the Couchbase extension */
use CouchbaseCluster;
use CouchbaseBucket;
use CouchbaseMetaDoc;
use CouchbaseException;

/* Imports from PHP core */
use UnexpectedValueException;

/**
 * NoSQL Storage Implementation for Couchbase Client
 * @author Safak Ozpinar <safak@gamegos.com>
 */
class Couchbase extends AbstractStorage
{
    /**
     * Couchbase cluster.
     * @var CouchbaseCluster
     */
    protected $cluster;

    /**
     * Current couchbase bucket.
     * @var CouchbaseBucket
     */
    protected $bucket;

    /**
     * Current couchbase bucket name.
     * @var string
     */
    protected $bucketName;

    /**
     * Key prefix.
     * @var string
     */
    protected $prefix;

    /**
     * Internal cache for key-casToken map.
     * @var array
     */
    protected $casTokens = [];

    /**
     * Construct.
     * @param array $params
     */
    public function __construct(array $params = [])
    {
        $dsn      = $params['dsn'] ?: 'http://127.0.0.1';
        $username = $params['username'] ?: '';
        $password = $params['password'] ?: '';
        $bucket   = $params['bucket'] ?: null;

        if (isset($params['prefix']) && is_string($params['prefix'])) {
            $this->prefix = $params['prefix'];
        }

        $this->cluster    = new CouchbaseCluster($dsn, $username, $password);
        $this->bucketName = $bucket;
    }

    /**
     * Get a Couchbase bucket instance.
     * @param  string $bucketName
     * @return \CouchbaseBucket
     */
    public function getBucket($bucketName = '')
    {
        if (empty($bucketName)) {
            $bucketName = $this->bucketName;
        }

        if ($bucketName !== $this->bucketName || null === $this->bucket) {
            $this->bucket     = $this->cluster->openBucket($bucketName);
            $this->bucketName = $this->bucket->getName();
        }

        return $this->bucket;
    }

    /**
     * Format a key to store.
     * @param  string $key
     * @return string
     */
    public function formatKey($key)
    {
        return $this->getPrefix() . $key;
    }

    /**
     * Get key prefix.
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Get type of a stored value.
     * @param  string $key
     * @throws \UnexpectedValueException Unknown response type.
     * @throws \CouchbaseException Unhandled case.
     * @return string|bool Primitive type name, class name or false (if key does not exist)
     */
    private function getValueType($key)
    {
        try {
            $result = $this->getBucket()->get($this->formatKey($key));
            if (!$this->validateResult($result)) {
                throw new UnexpectedValueException(sprintf('Couchbase error (%s).', $result->error));
            }
            $type = gettype($result->value);
            if ('object' == $type) {
                $type = get_class($result->value);
            }
            return $type;
        } catch (CouchbaseException $e) {
            if ($e->getCode() !== COUCHBASE_KEY_ENOENT) {
                throw $e;
            }
        }
        return false;
    }

    /**
     * Validate a couchbase operation result.
     * @param  mixed $result
     * @throws \UnexpectedValueException Unexpected couchbase response type
     * @return bool Tells the result has an error.
     */
    protected function validateResult($result)
    {
        if (! $result instanceof CouchbaseMetaDoc) {
            $type = gettype($result);
            if ('object' == $type) {
                $type = get_class($result);
            }
            throw new UnexpectedValueException(sprintf('Unexpected couchbase response type (%s).', $type));
        }
        return null === $result->error;
    }

    /**
     * {@inheritdoc}
     */
    protected function hasInternal($key)
    {
        return $this->getValueType($key) !== false;
    }

    /**
     * {@inheritdoc}
     */
    protected function getInternal($key, & $casToken = null)
    {
        $key = $this->formatKey($key);
        try {
            $result = $this->getBucket()->get($key);
            if (!$this->validateResult($result)) {
                throw new UnexpectedValueException(sprintf('Couchbase error (%s).', $result->error));
            }
            $casToken = $result->cas;
            return $result->value;
        } catch (CouchbaseException $e) {
            if ($e->getCode() === COUCHBASE_KEY_ENOENT) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getMultiInternal(array $keys, array & $casTokens = null)
    {
        $realKeys  = array_map([$this, 'formatKey'], $keys);
        $results   = $this->getBucket()->get($realKeys);
        $values    = [];
        $casTokens = [];
        $keyStart  = strlen($this->getPrefix());

        foreach ($results as $realKey => $result) {
            if (!$this->validateResult($result)) {
                if ($result->error instanceof CouchbaseException) {
                    if ($result->error->getCode() === COUCHBASE_KEY_ENOENT) {
                        continue;
                    }
                    throw new UnexpectedValueException(
                        sprintf('Couchbase error (%s).', $result->error->getMessage()),
                        $result->error->getCode(),
                        $result->error
                    );
                }
                throw new UnexpectedValueException(sprintf('Couchbase error (%s).', $result->error));
            }
            $key             = substr($realKey, $keyStart);
            $values[$key]    = $result->value;
            $casTokens[$key] = $result->cas;
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    protected function addInternal($key, $value, $expiry = 0)
    {
        $options = [];
        if (func_num_args() > 2) {
            $options['expiry'] = $expiry;
        }

        try {
            $result = $this->getBucket()->insert($this->formatKey($key), $value, $options);
            return $this->validateResult($result);
        } catch (CouchbaseException $e) {
            if ($e->getCode() === COUCHBASE_KEY_EEXISTS) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function setInternal($key, $value, $expiry = 0, $casToken = null)
    {
        if (func_num_args() > 3 && is_string($casToken)) {
            return $this->casInternal($casToken, $key, $value, $expiry);
        }

        $options = [];
        if (func_num_args() > 2) {
            $options['expiry'] = $expiry;
        }

        $result = $this->getBucket()->upsert($this->formatKey($key), $value, $options);
        return $this->validateResult($result);
    }

    /**
     * {@inheritdoc}
     */
    protected function casInternal($casToken, $key, $value, $expiry = 0)
    {
        $options = [
            // CAS token may set as null by the parent class, but Couchbase does not allow a null CAS value.
            'cas' => (string) $casToken,
        ];
        if (func_num_args() > 3) {
            $options['expiry'] = $expiry;
        }

        try {
            $result = $this->getBucket()->replace($this->formatKey($key), $value, $options);
            return $this->validateResult($result);
        } catch (CouchbaseException $e) {
            if ($e->getCode() === COUCHBASE_KEY_EEXISTS) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteInternal($key)
    {
        try {
            $result = $this->getBucket()->remove($this->formatKey($key));
            return $this->validateResult($result);
        } catch (CouchbaseException $e) {
            if ($e->getCode() === COUCHBASE_KEY_ENOENT) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function appendInternal($key, $value, $expiry = 0)
    {
        $valueType = $this->getValueType($key);
        if (false !== $valueType && 'string' !== $valueType) {
            throw new UnexpectedValueException(sprintf(
                'Method append() requires existing value to be string, %s found.',
                $valueType
            ));
        }

        $options = [];
        if (func_num_args() > 2) {
            $options['expiry'] = $expiry;
        }

        try {
            $result = $this->getBucket()->append($this->formatKey($key), $value, $options);
            return $this->validateResult($result);
        } catch (CouchbaseException $e) {
            if ($e->getCode() === COUCHBASE_NOT_STORED) {
                // Try to create new key.
                if ($this->addInternal($key, $value, $expiry)) {
                    return true;
                }
                // Another client or process might have created the key, try to append again.
                // @todo this may cause an infinite recursion, try to limit recursive calls.
                return $this->appendInternal($key, $value, $expiry);
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function incrementInternal($key, $offset = 1, $initial = 0, $expiry = 0)
    {
        $options = [];
        if (func_num_args() > 2) {
            $options['initial'] = $initial;
            if (func_num_args() > 3) {
                $options['expiry'] = $expiry;
            }
        }

        try {
            $result = $this->getBucket()->counter($this->formatKey($key), $offset, $options);
            return $this->validateResult($result);
        } catch (CouchbaseException $e) {
            if ($e->getCode() === COUCHBASE_DELTA_BADVAL) {
                $oldValue = $this->getInternal($key);
                if (!is_int($oldValue)) {
                    throw new UnexpectedValueException(sprintf(
                        'Method increment() requires existing value to be integer, %s found.',
                        gettype($oldValue)
                    ));
                }
            }
            throw $e;
        }
    }
}
