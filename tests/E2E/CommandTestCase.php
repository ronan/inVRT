<?php

namespace Tests\E2E;

use App\Commands\ApproveCommand;
use App\Commands\BaselineCommand;
use App\Commands\ConfigCommand;
use App\Commands\CrawlCommand;
use App\Commands\InitCommand;
use App\Commands\ReferenceCommand;
use App\Commands\TestCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Fixtures\TestProjectFixture;

use \Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for E2E command tests
 *
 * Provides common infrastructure for testing invrt CLI commands:
 * - Fixture management (temp project directories)
 * - Command application setup
 * - Command execution and output capture
 * - Common assertion helpers
 */
abstract class CommandTestCase extends TestCase
{
    protected TestProjectFixture $fixture;
    protected Application $app;
    protected ?CommandTester $commandTester = null;
    protected string $strayOutput = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Set the project directory as the working directory for the test
        $this->setUpFixture();

        // Create application with all commands
        $this->app = new Application('inVRT CLI', '1.0.2');

        $this->app->addCommand(new InitCommand());
        $this->app->addCommand(new ApproveCommand());
        $this->app->addCommand(new BaselineCommand());
        $this->app->addCommand(new CrawlCommand());
        $this->app->addCommand(new ReferenceCommand());
        $this->app->addCommand(new TestCommand());
        $this->app->addCommand(new ConfigCommand());

        // Make application not exit on exception
        $this->app->setCatchExceptions(false);
    }

    protected function tearDown(): void
    {
        // Unset env var; output is preserved in scratch/tmp/ for inspection
        $this->fixture->unsetEnvironmentVariable();

        parent::tearDown();
    }

    protected function setUpFixture(bool $clear = true): void
    {
        // Use scratch/tmp/{ClassName}/{testName} for deterministic, inspectable output
        $class = (new \ReflectionClass($this))->getShortName();
        $base = dirname(__DIR__, 2) . '/scratch/tmp/' . $class . '/' . $this->name();

        // Create fixture
        $this->fixture = new TestProjectFixture($base);

        if ($clear) {
            $this->fixture->cleanup();
        }

        $this->fixture->create();
        $this->fixture->setEnvironmentVariable();
    }

    /**
     * Execute a command and return the output
     *
     * @param string $commandName The command to run (e.g., 'init', 'crawl')
     * @param array $input Additional arguments/options
     * @param array<string, mixed> $options  CommandTester options (e.g. ['verbosity' => OutputInterface::VERBOSITY_VERBOSE])
     * @return CommandTester The tester with the command result
     */
    protected function executeCommand(string $commandName, array $input = [], array $options = []): CommandTester
    {
        $command = $this->app->find($commandName);
        $this->commandTester = new CommandTester($command);

        // Merge default options
        $testInput = array_merge(['command' => $commandName], $input);

        // Execute the command
        $this->commandTester->execute($testInput, $options + [
            'interactive' => false,
            'verbosity' => OutputInterface::VERBOSITY_DEBUG,
        ]);

        return $this->commandTester;
    }

    /**
     * Execute a command with interactive answers.
     *
     * @param list<string> $inputs Prompt responses for QuestionHelper
     * @param array<string, mixed> $input
     * @param array<string, mixed> $options
     */
    protected function executeCommandWithInputs(string $commandName, array $inputs, array $input = [], array $options = []): CommandTester
    {
        $command = $this->app->find($commandName);
        $this->commandTester = new CommandTester($command);
        $this->commandTester->setInputs($inputs);

        $testInput = array_merge(['command' => $commandName], $input);
        $this->commandTester->execute($testInput, $options + [
            'interactive' => true,
            'verbosity' => OutputInterface::VERBOSITY_DEBUG,
        ]);

        return $this->commandTester;
    }

    /**
     * Get the exit code from the last command execution
     */
    protected function getExitCode(): int
    {
        $this->assertNotNull($this->commandTester, 'No command has been executed yet');
        return $this->commandTester->getStatusCode();
    }

    /**
     * Get the display output from the last command execution
     */
    protected function getOutput(): string
    {
        $this->assertNotNull($this->commandTester, 'No command has been executed yet');
        return $this->commandTester->getDisplay();
    }

    /**
     * Assert command succeeded (exit code 0)
     */
    protected function assertCommandSuccess(): void
    {
        $this->assertEquals(
            0,
            $this->getExitCode(),
            "Command failed with exit code {$this->getExitCode()}\n\nOutput:\n" . $this->getOutput(),
        );
    }

    /**
     * Assert command failed (non-zero exit code)
     */
    protected function assertCommandFailure(?int $expectedCode = null): void
    {
        $exitCode = $this->getExitCode();
        if ($expectedCode === null) {
            $this->assertNotEquals(
                0,
                $exitCode,
                "Command succeeded but was expected to fail\n\nOutput:\n" . $this->getOutput(),
            );
        } else {
            $this->assertEquals(
                $expectedCode,
                $exitCode,
                "Command failed with exit code $exitCode but expected $expectedCode\n\nOutput:\n" . $this->getOutput(),
            );
        }
    }

    /**
     * Assert output contains a string
     */
    protected function assertOutputContains(string $expected): void
    {
        $output = $this->getOutput();
        $this->assertStringContainsString(
            $expected,
            $output,
            "Output does not contain expected string:\n$expected\n\nActual output:\n$output",
        );
    }

    /**
     * Assert output does not contain a string
     */
    protected function assertOutputNotContains(string $notExpected): void
    {
        $output = $this->getOutput();
        $this->assertStringNotContainsString(
            $notExpected,
            $output,
            "Output contains unexpected string:\n$notExpected\n\nActual output:\n$output",
        );
    }

    /**
     * Assert config file was created
     */
    protected function assertConfigFileExists(): void
    {
        $this->assertTrue(
            $this->fixture->hasConfig(),
            'Config file does not exist at ' . $this->fixture->getConfigPath(),
        );
    }

    /**
     * Assert config file does not exist
     */
    protected function assertConfigFileNotExists(): void
    {
        $this->assertFalse(
            $this->fixture->hasConfig(),
            'Config file exists but was not expected at ' . $this->fixture->getConfigPath(),
        );
    }

    /**
     * Assert config has a specific value
     */
    protected function assertConfigValue(string $key, $expectedValue): void
    {
        $config = $this->fixture->readConfig();
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            $this->assertArrayHasKey($k, $value, "Config missing key: $k in path: $key");
            $value = $value[$k];
        }

        $this->assertEquals(
            $expectedValue,
            $value,
            sprintf(
                'Config value mismatch at %s. Expected: %s, Got: %s',
                $key,
                var_export($expectedValue, true),
                var_export($value, true),
            ),
        );
    }

    /**
     * Get the fixture for direct access
     */
    protected function getFixture(): TestProjectFixture
    {
        return $this->fixture;
    }

    /**
     * Get the console application
     */
    protected function getApplication(): Application
    {
        return $this->app;
    }

    /**
     * Execute a command and capture stray subprocess output (from passthru, etc)
     *
     * This uses output buffering to capture output that bypasses Symfony's output interface,
     * such as output from bash subprocesses called via passthru().
     *
     * @param string $commandName The command to run
     * @param array $input Additional arguments/options
     * @return CommandTester The tester with the command result
     */
    protected function executeCommandWithOutputCapture(string $commandName, array $input = []): CommandTester
    {
        // Start buffering stray output
        ob_start();

        try {
            // Execute the command normally
            $this->executeCommand($commandName, $input);
        } finally {
            // Capture any stray output and store it
            $this->strayOutput = ob_get_clean();
        }

        return $this->commandTester;
    }

    /**
     * Get the captured stray output from the last command execution
     */
    protected function getStrayOutput(): string
    {
        return $this->strayOutput;
    }

    /**
     * Assert captured stray output contains expected string
     */
    protected function assertStrayOutputContains(string $expected): void
    {
        $this->assertStringContainsString(
            $expected,
            $this->strayOutput,
            "Stray output does not contain expected string:\n$expected\n\nActual stray output:\n" . substr($this->strayOutput, 0, 500),
        );
    }

    /**
     * Assert captured stray output doesn't contain unexpected string
     */
    protected function assertStrayOutputNotContains(string $notExpected): void
    {
        $this->assertStringNotContainsString(
            $notExpected,
            $this->strayOutput,
            "Stray output contains unexpected string:\n$notExpected",
        );
    }

    /**
     * Assert stray output contains initialization messages (for init command tests)
     */
    protected function assertStrayOutputHasInitMessages(): void
    {
        $this->assertStrayOutputContains('Initializing InVRT');
    }

    /**
     * Assert stray output contains crawling messages (for crawl command tests)
     */
    protected function assertStrayOutputHasCrawlingMessages(): void
    {
        $this->assertStrayOutputContains('Crawling');
    }
}
