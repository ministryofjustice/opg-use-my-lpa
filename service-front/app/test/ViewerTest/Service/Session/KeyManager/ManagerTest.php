<?php

declare(strict_types=1);

namespace ViewerTest\Service\Session\KeyManager;

use Aws\Result as AwsResult;
use Aws\SecretsManager\SecretsManagerClient;
use Viewer\Service\Session\KeyManager\KeyCache;
use Viewer\Service\Session\KeyManager\Manager;
use Viewer\Service\Session\KeyManager\KeyNotFoundException;
use Viewer\Service\Session\KeyManager\ThrottledRefreshException;
use ParagonIE\Halite\Alerts\InvalidKey;
use ParagonIE\HiddenString\HiddenString;
use Prophecy\Argument;
use PHPUnit\Framework\TestCase;

class ManagerTest extends TestCase
{
    const NAME_OF_SECRET = 'name-of-secret';

    private $cacheProphercy;
    private $secretsManagerClientProphercy;

    public function setUp()
    {
        $this->cacheProphercy = $this->prophesize(KeyCache::class);

        //---

        $this->secretsManagerClientProphercy = $this->prophesize(SecretsManagerClient::class);
    }

    private function getManagerInstance()
    {
        return new Manager(self::NAME_OF_SECRET, $this->secretsManagerClientProphercy->reveal(), $this->cacheProphercy->reveal());
    }

    public function testCanInstantiate()
    {
        $m = $this->getManagerInstance();
        $this->assertInstanceOf(Manager::class, $m);
    }

    //-----------------------------------------------------------------------------------------------------------------
    // Testing of updateSecrets(); without throttling

    /*
     * We expect Secrets Manager to return an Aws\Result
     */
    public function testExceptionWhenNullResponseFromSecretsManager()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid response from Secrets Manager; missing SecretString');

        //---

        $awsResultProphercy = $this->prophesize(AwsResult::class);

        $awsResultProphercy->hasKey('SecretString')->willReturn(false);

        $this->secretsManagerClientProphercy->getSecretValue([
            'SecretId' => self::NAME_OF_SECRET
        ])->willReturn($awsResultProphercy->reveal());

        //---

        $m = $this->getManagerInstance();
        $m->getCurrentKey();
    }

    /*
     * We expect that result to hold a SecretString, containing valid JSON.
     * If it's not valid JSON, we expect an exception.
     */
    public function testExceptionWhenBadResponseFromSecretsManager()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid response from Secrets Manager; invalid JSON');

        //---

        $awsResultProphercy = $this->prophesize(AwsResult::class);

        $awsResultProphercy->hasKey('SecretString')->willReturn(true);
        $awsResultProphercy->get('SecretString')->willReturn('not-valid-json');

        $this->secretsManagerClientProphercy->getSecretValue([
            'SecretId' => self::NAME_OF_SECRET
        ])->willReturn($awsResultProphercy->reveal());

        //---

        $m = $this->getManagerInstance();
        $m->getCurrentKey();
    }

    /*
     * We expect an exception if the Keys are not valid
     */
    public function testExceptionWhenResponseContainersInvalidKey()
    {
        $this->expectException(InvalidKey::class);
        $this->expectExceptionMessage('Encryption key must be CRYPTO_STREAM_KEYBYTES bytes long');

        //---

        $testId = '12';
        $testMaterial = '1111'; // Code too short.

        $awsResultProphercy = $this->prophesize(AwsResult::class);

        $awsResultProphercy->hasKey('SecretString')->willReturn(true);

        $awsResultProphercy->get('SecretString')->willReturn(json_encode([
            $testId => $testMaterial,
            '4' => '0000000000000000000000000000000000000000000000000000000000000000',
        ]));

        $this->secretsManagerClientProphercy->getSecretValue([
            'SecretId' => self::NAME_OF_SECRET
        ])->willReturn($awsResultProphercy->reveal());

        //---

        $m = $this->getManagerInstance();
        $m->getCurrentKey();
    }

    /*
     * We expect an exception if we don't get exactly two keys
     */
    public function testExceptionWhenResponseHasTooFewKeys()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to load session keys');

        //---

        $awsResultProphercy = $this->prophesize(AwsResult::class);

        $awsResultProphercy->hasKey('SecretString')->willReturn(true);

        $awsResultProphercy->get('SecretString')->willReturn(json_encode([
            '4' => '0000000000000000000000000000000000000000000000000000000000000000',
        ]));

        $this->secretsManagerClientProphercy->getSecretValue([
            'SecretId' => self::NAME_OF_SECRET
        ])->willReturn($awsResultProphercy->reveal());

        //---

        $m = $this->getManagerInstance();
        $m->getCurrentKey();
    }

    /*
     * We expect an exception if we don't get exactly two keys
     */
    public function testExceptionWhenResponseHasTooManyKeys()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to load session keys');

        //---

        $awsResultProphercy = $this->prophesize(AwsResult::class);

        $awsResultProphercy->hasKey('SecretString')->willReturn(true);

        $awsResultProphercy->get('SecretString')->willReturn(json_encode([
            '4' => '0000000000000000000000000000000000000000000000000000000000000000',
            '5' => '1111111111111111111111111111111111111111111111111111111111111111',
            '14' => '2222222222222222222222222222222222222222222222222222222222222222',
        ]));

        $this->secretsManagerClientProphercy->getSecretValue([
            'SecretId' => self::NAME_OF_SECRET
        ])->willReturn($awsResultProphercy->reveal());

        //---

        $m = $this->getManagerInstance();
        $m->getCurrentKey();
    }

    /*
     * If a valid response from Secrets
     */
    public function testValidResponseFromSecretsManager()
    {
        $testId = '12';
        $testMaterial = '1111111111111111111111111111111111111111111111111111111111111111';

        $awsResultProphercy = $this->prophesize(AwsResult::class);

        $awsResultProphercy->hasKey('SecretString')->willReturn(true);

        // Defined this way around as we'd expect the 'biggest' number to be the current key
        $awsResultProphercy->get('SecretString')->willReturn(json_encode([
            $testId => $testMaterial,
            '4' => '0000000000000000000000000000000000000000000000000000000000000000',
        ]));

        $this->secretsManagerClientProphercy->getSecretValue([
            'SecretId' => self::NAME_OF_SECRET
        ])->willReturn($awsResultProphercy->reveal());

        //---

        // Cache will be checked first keys first
        $this->cacheProphercy->get(Manager::CACHE_SESSION_KEY)->willReturn(false);

        // Test cache methods were called
        $this->cacheProphercy->store(Manager::CACHE_SESSION_KEY, Argument::type('array'), Argument::type('int'))->shouldBeCalled();
        $this->cacheProphercy->store(Manager::CACHE_SESSION_UPDATED_KEY, Argument::type('int'))->shouldBeCalled();

        //---

        $m = $this->getManagerInstance();
        $key = $m->getCurrentKey();

        $this->assertEquals($testId, $key->getId());
        $this->assertEquals(hex2bin($testMaterial), $key->getKeyMaterial());
    }

    /*
     * If a valid response from Secrets, but the other way around to the above.
     */
    public function testValidResponseFromSecretsManagerIfArrayIsReversed()
    {
        $testId = '12';
        $testMaterial = '1111111111111111111111111111111111111111111111111111111111111111';

        $awsResultProphercy = $this->prophesize(AwsResult::class);

        $awsResultProphercy->hasKey('SecretString')->willReturn(true);

        // Defined this way around as we'd expect the 'biggest' number to be the current key
        $awsResultProphercy->get('SecretString')->willReturn(json_encode([
            '4' => '0000000000000000000000000000000000000000000000000000000000000000',
            $testId => $testMaterial,
        ]));

        $this->secretsManagerClientProphercy->getSecretValue([
            'SecretId' => self::NAME_OF_SECRET
        ])->willReturn($awsResultProphercy->reveal());

        //---

        // Cache will be checked first keys first
        $this->cacheProphercy->get(Manager::CACHE_SESSION_KEY)->willReturn(false);

        // Test cache methods were called
        $this->cacheProphercy->store(Manager::CACHE_SESSION_KEY, Argument::type('array'), Argument::type('int'))->shouldBeCalled();
        $this->cacheProphercy->store(Manager::CACHE_SESSION_UPDATED_KEY, Argument::type('int'))->shouldBeCalled();

        //---

        $m = $this->getManagerInstance();
        $key = $m->getCurrentKey();

        $this->assertEquals($testId, $key->getId());
        $this->assertEquals(hex2bin($testMaterial), $key->getKeyMaterial());
    }


    //-----------------------------------------------------------------------------------------------------------------
    // Test looking up valid keys in a valid cache

    /*
     * The success case; the cache contains 2 keys, current and previous. We are able to:
     *  - Lookup the current key (with the highest index value)
     *  - Look both keys explicitly using their ID.
     */
    public function testLookingUpValidKeys()
    {
        $previousId = 7;
        $previousMaterial = '0000000000000000000000000000000000000000000000000000000000000000';

        $currentId = 12;
        $currentMaterial = '1111111111111111111111111111111111111111111111111111111111111111';

        // Mock key cache data
        $data = [
            $previousId => new HiddenString(hex2bin($previousMaterial)),
            $currentId  => new HiddenString(hex2bin($currentMaterial)),
        ];

        // Cache will be checked first keys first
        $this->cacheProphercy->get(Manager::CACHE_SESSION_KEY)->willReturn($data);

        $m = $this->getManagerInstance();

        //---

        // Current key is the latest key
        $key = $m->getCurrentKey();

        $this->assertEquals($currentId, $key->getId());
        $this->assertEquals(hex2bin($currentMaterial), $key->getKeyMaterial());

        //---

        // We can also explicitly lookup the current key
        $key = $m->getKeyId($currentId);

        $this->assertEquals($currentId, $key->getId());
        $this->assertEquals(hex2bin($currentMaterial), $key->getKeyMaterial());

        //---

        // We can also lookup the last key
        $key = $m->getKeyId($previousId);

        $this->assertEquals($previousId, $key->getId());
        $this->assertEquals(hex2bin($previousMaterial), $key->getKeyMaterial());
    }

    //-----------------------------------------------------------------------------------------------------------------
    // Test looking up a key not in the cache

    /*
     * We lookup a key that's not in the cache. The cache will get refreshed, but will see that we've very recently
     * already done a cache lookup. A ThrottledRefreshException will therefor be thrown.
     */
    public function testLookingUpMissingKeyThrottled()
    {
        $this->expectException(ThrottledRefreshException::class);
        $this->expectExceptionMessage('Too many attempts to refresh the session keys');

        //---

        $previousId = 7;
        $previousMaterial = '0000000000000000000000000000000000000000000000000000000000000000';

        $currentId = 12;
        $currentMaterial = '1111111111111111111111111111111111111111111111111111111111111111';

        // Mock key cache data
        $data = [
            $previousId => new HiddenString(hex2bin($previousMaterial)),
            $currentId => new HiddenString(hex2bin($currentMaterial)),
        ];

        // Cache will be checked first keys first
        $this->cacheProphercy->get(Manager::CACHE_SESSION_KEY)->willReturn($data);

        // Return that we've just done a cache lookup
        $this->cacheProphercy->get(Manager::CACHE_SESSION_UPDATED_KEY)->willReturn(time());

        $m = $this->getManagerInstance();

        $m->getKeyId(14);    // Key not in cache
    }


    /*
     * We lookup a key that's not in the cache. The cache will get refreshed and will not be throttled.
     * Secrets Manager returns the exact same keys that were already in the cache, thus the request key
     * will be 'Not Found'.
     */
    public function testLookingUpMissingKeyUnthrottledNoNewKey()
    {
        $invalidId = 14;

        $this->expectException(KeyNotFoundException::class);
        $this->expectExceptionMessage('Unable to find key for ID: '.$invalidId);

        //---

        // Mock key cache data
        $data = [
            7 => new HiddenString(hex2bin('0000000000000000000000000000000000000000000000000000000000000000')),
            8 => new HiddenString(hex2bin('1111111111111111111111111111111111111111111111111111111111111111')),
        ];

        // Cache will be checked first keys first
        $this->cacheProphercy->get(Manager::CACHE_SESSION_KEY)->willReturn($data);

        // Return that the last lookup was a little while ago
        $this->cacheProphercy->get(Manager::CACHE_SESSION_UPDATED_KEY)->willReturn(time() - 300);

        //-----------------------------------------------------

        $awsResultProphercy = $this->prophesize(AwsResult::class);

        $awsResultProphercy->hasKey('SecretString')->willReturn(true);

        // Returns the exact same set of keys that was in the cache
        $awsResultProphercy->get('SecretString')->willReturn(json_encode([
            '7' => '0000000000000000000000000000000000000000000000000000000000000000',
            '8' => '1111111111111111111111111111111111111111111111111111111111111111',
        ]));

        $this->secretsManagerClientProphercy->getSecretValue([
            'SecretId' => self::NAME_OF_SECRET
        ])->willReturn($awsResultProphercy->reveal());

        // The cache will be updated
        $this->cacheProphercy->store(Manager::CACHE_SESSION_KEY, Argument::type('array'), Argument::type('int'))->shouldBeCalled();
        $this->cacheProphercy->store(Manager::CACHE_SESSION_UPDATED_KEY, Argument::type('int'))->shouldBeCalled();

        //-----------------------------------------------------

        $m = $this->getManagerInstance();

        $m->getKeyId($invalidId);    // Key not in cache
    }

    /*
     * We lookup a key that's not in the cache. The cache will get refreshed and will not be throttled.
     * Secrets Manager returns and updated key set which included the new key. It'll then be returned.
     */
    public function testLookingUpMissingKeyUnthrottledAndFindIt()
    {
        $newId = 14;
        $newMaterial = '2222222222222222222222222222222222222222222222222222222222222222';

        // Mock key cache data
        $data = [
            7 => new HiddenString(hex2bin('0000000000000000000000000000000000000000000000000000000000000000')),
            8 => new HiddenString(hex2bin('1111111111111111111111111111111111111111111111111111111111111111')),
        ];

        // Cache will be checked first keys first
        $this->cacheProphercy->get(Manager::CACHE_SESSION_KEY)->willReturn($data);

        // Return that the last lookup was a little while ago
        $this->cacheProphercy->get(Manager::CACHE_SESSION_UPDATED_KEY)->willReturn(time() - 300);

        //-----------------------------------------------------

        $awsResultProphercy = $this->prophesize(AwsResult::class);

        $awsResultProphercy->hasKey('SecretString')->willReturn(true);

        // Returns the exact same set of keys that was in the cache
        $awsResultProphercy->get('SecretString')->willReturn(json_encode([
            '8' => '1111111111111111111111111111111111111111111111111111111111111111',
            "$newId" => $newMaterial,
        ]));

        $this->secretsManagerClientProphercy->getSecretValue([
            'SecretId' => self::NAME_OF_SECRET
        ])->willReturn($awsResultProphercy->reveal());

        // The cache will be updated
        $this->cacheProphercy->store(Manager::CACHE_SESSION_KEY, Argument::type('array'), Argument::type('int'))->shouldBeCalled();
        $this->cacheProphercy->store(Manager::CACHE_SESSION_UPDATED_KEY, Argument::type('int'))->shouldBeCalled();

        //-----------------------------------------------------

        $m = $this->getManagerInstance();

        $key = $m->getKeyId($newId);    // Key not in cache

        $this->assertEquals($newId, $key->getId());
        $this->assertEquals(hex2bin($newMaterial), $key->getKeyMaterial());
    }
}
