<?php

namespace Tests\E2E;

/**
 * Abstract base class for E2E tests that require a PHP built-in webserver.
 *
 * Starts a server once per test class (setUpBeforeClass), serving from
 * tests/fixtures/website/, and stops it after all tests in the class run.
 */
abstract class WebCommandTestCase extends CommandTestCase
{
    protected static int $serverPort = 0;

    /** @var resource|null */
    private static mixed $serverProcess = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$serverPort = self::findFreePort();

        $websiteDir = dirname(__DIR__) . '/fixtures/website';
        $desc = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
        $pipes = [];

        self::$serverProcess = proc_open(
            sprintf('php -S 127.0.0.1:%d -t %s', self::$serverPort, escapeshellarg($websiteDir)),
            $desc,
            $pipes,
        );

        self::waitForServer(self::$serverPort);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverProcess !== null) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
            self::$serverProcess = null;
        }

        parent::tearDownAfterClass();
    }

    private static function findFreePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $port = (int) parse_url(stream_socket_get_name($sock, false), PHP_URL_PORT);
        fclose($sock);
        return $port;
    }

    private static function waitForServer(int $port, float $timeout = 3.0): void
    {
        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            $conn = @stream_socket_client("tcp://127.0.0.1:$port", $errno, $errstr, 0.1);
            if ($conn !== false) {
                fclose($conn);
                return;
            }
            usleep(50_000);
        }
        throw new \RuntimeException("PHP webserver did not start on port $port within {$timeout}s");
    }

    protected function webserverUrl(): string
    {
        return 'http://127.0.0.1:' . self::$serverPort;
    }

    /** Write config + crawled_urls.txt pointing at the test webserver. */
    protected function setupFixture($clear = false): void
    {
        if ($clear) {
            $this->fixture->cleanup();
            $this->fixture->create();
        }
        $this->fixture->writeConfig([
            'environments' => [
                'local' => ['url' => $this->webserverUrl()],
            ],
        ]);
        $this->fixture->writeCrawledUrlsFile('local', 'anonymous', ['/', '/about.html']);
    }
}
