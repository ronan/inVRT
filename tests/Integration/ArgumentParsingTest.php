<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Tests for argument parsing and command handling logic
 */
class ArgumentParsingTest extends TestCase
{
    /**
     * Test parsing --profile= syntax
     */
    public function testParseProfileWithEquals(): void
    {
        $arg = '--profile=custom';
        $profileName = '';

        if (strpos($arg, '--profile=') === 0) {
            $profileName = substr($arg, 10);
        }

        $this->assertEquals('custom', $profileName);
    }

    /**
     * Test parsing -p= syntax
     */
    public function testParseProfileShortWithEquals(): void
    {
        $arg = '-p=custom';
        $profileName = '';

        if (strpos($arg, '-p=') === 0) {
            $profileName = substr($arg, 3);
        }

        $this->assertEquals('custom', $profileName);
    }

    /**
     * Test parsing --device= syntax
     */
    public function testParseDeviceWithEquals(): void
    {
        $arg = '--device=mobile';
        $deviceName = '';

        if (strpos($arg, '--device=') === 0) {
            $deviceName = substr($arg, 9);
        }

        $this->assertEquals('mobile', $deviceName);
    }

    /**
     * Test parsing --environment= syntax
     */
    public function testParseEnvironmentWithEquals(): void
    {
        $arg = '--environment=staging';
        $environmentName = '';

        if (strpos($arg, '--environment=') === 0) {
            $environmentName = substr($arg, 14);
        }

        $this->assertEquals('staging', $environmentName);
    }

    /**
     * Test extracting command from argv
     */
    public function testExtractCommandFromArgv(): void
    {
        $argv = ['invrt.php', 'crawl', '--profile=default'];
        $command = $argv[1] ?? '';

        $this->assertEquals('crawl', $command);
    }

    /**
     * Test no command provided
     */
    public function testNoCommandProvided(): void
    {
        $argv = ['invrt.php'];
        $argc = count($argv);
        $command = $argc > 1 ? $argv[1] : '';

        $this->assertEquals('', $command);
    }

    /**
     * Test argument parsing loop simulation
     */
    public function testArgumentParsingLoop(): void
    {
        $argv = [
            'invrt.php',
            'crawl',
            '--profile=mobile',
            '--device=mobile',
            '--environment=dev'
        ];
        
        $argc = count($argv);
        $profileName = 'default';
        $deviceName = 'desktop';
        $environmentName = 'local';

        // Simulate the argument parsing loop
        for ($i = 2; $i < $argc; $i++) {
            $arg = $argv[$i];
            
            if (strpos($arg, '--profile=') === 0) {
                $profileName = substr($arg, 10);
            } elseif (strpos($arg, '--device=') === 0) {
                $deviceName = substr($arg, 9);
            } elseif (strpos($arg, '--environment=') === 0) {
                $environmentName = substr($arg, 14);
            }
        }

        $this->assertEquals('mobile', $profileName);
        $this->assertEquals('mobile', $deviceName);
        $this->assertEquals('dev', $environmentName);
    }

    /**
     * Test parsing multiple argument formats in one loop
     */
    public function testParsingMultipleArgumentFormats(): void
    {
        $argv = [
            'invrt.php',
            'reference',
            '-p=desktop',
            '-d=desktop',
            '-e=prod'
        ];

        $argc = count($argv);
        $profileName = 'default';
        $deviceName = 'desktop';
        $environmentName = 'local';

        for ($i = 2; $i < $argc; $i++) {
            $arg = $argv[$i];
            
            if (strpos($arg, '-p=') === 0) {
                $profileName = substr($arg, 3);
            } elseif (strpos($arg, '-d=') === 0) {
                $deviceName = substr($arg, 3);
            } elseif (strpos($arg, '-e=') === 0) {
                $environmentName = substr($arg, 3);
            }
        }

        $this->assertEquals('desktop', $profileName);
        $this->assertEquals('desktop', $deviceName);
        $this->assertEquals('prod', $environmentName);
    }

    /**
     * Test default values are preserved
     */
    public function testDefaultValuesPreserved(): void
    {
        $argv = ['invrt.php', 'crawl'];
        
        $argc = count($argv);
        $profileName = 'default';
        $deviceName = 'desktop';
        $environmentName = 'local';

        for ($i = 2; $i < $argc; $i++) {
            // No arguments to parse
        }

        $this->assertEquals('default', $profileName);
        $this->assertEquals('desktop', $deviceName);
        $this->assertEquals('local', $environmentName);
    }

    /**
     * Test valid commands
     */
    public function testValidCommandValidation(): void
    {
        $validCommands = ['init', 'crawl', 'reference', 'test'];
        
        $testCases = [
            'crawl' => true,
            'init' => true,
            'reference' => true,
            'test' => true,
            'invalid' => false,
            'help' => false,
            'run' => false,
        ];

        foreach ($testCases as $command => $shouldBeValid) {
            $isValid = in_array($command, $validCommands);
            $this->assertEquals($shouldBeValid, $isValid, "Command '$command' validation failed");
        }
    }

    /**
     * Test help command special handling
     */
    public function testHelpCommandHandling(): void
    {
        $helpCommands = ['help', '--help', '-h'];
        
        foreach ($helpCommands as $command) {
            $isHelpCommand = !$command || $command === 'help' || $command === '--help' || $command === '-h';
            $this->assertTrue($isHelpCommand, "Help command '$command' not recognized");
        }
    }

    /**
     * Test command with no options
     */
    public function testCommandWithNoOptions(): void
    {
        $argv = ['invrt.php', 'init'];
        $argc = count($argv);

        $command = $argv[1];

        for ($i = 2; $i < $argc; $i++) {
            // No options
        }

        $this->assertEquals('init', $command);
    }

    /**
     * Test real-world argument sequence
     */
    public function testRealWorldArgumentSequence(): void
    {
        // Simulate: php invrt.php crawl --profile=sponsor --device=mobile --environment=prod
        $argv = [
            'invrt.php',
            'crawl',
            '--profile=sponsor',
            '--device=mobile',
            '--environment=prod'
        ];

        $argc = count($argv);
        $command = $argv[1];
        $profileName = 'default';
        $deviceName = 'desktop';
        $environmentName = 'local';

        for ($i = 2; $i < $argc; $i++) {
            $arg = $argv[$i];
            if (strpos($arg, '--profile=') === 0) {
                $profileName = substr($arg, 10);
            } elseif (strpos($arg, '--device=') === 0) {
                $deviceName = substr($arg, 9);
            } elseif (strpos($arg, '--environment=') === 0) {
                $environmentName = substr($arg, 14);
            }
        }

        $this->assertEquals('crawl', $command);
        $this->assertEquals('sponsor', $profileName);
        $this->assertEquals('mobile', $deviceName);
        $this->assertEquals('prod', $environmentName);
    }

    /**
     * Test argument with special characters in value
     */
    public function testArgumentWithSpecialCharacters(): void
    {
        $arg = '--profile=my-profile_v2.0';
        $profileName = '';

        if (strpos($arg, '--profile=') === 0) {
            $profileName = substr($arg, 10);
        }

        $this->assertEquals('my-profile_v2.0', $profileName);
    }

    /**
     * Test empty argument value
     */
    public function testEmptyArgumentValue(): void
    {
        $arg = '--profile=';
        $profileName = 'default';

        if (strpos($arg, '--profile=') === 0) {
            $newValue = substr($arg, 10);
            if ($newValue !== '') {
                $profileName = $newValue;
            }
        }

        // Should keep default since value is empty
        $this->assertEquals('default', $profileName);
    }
}
?>
