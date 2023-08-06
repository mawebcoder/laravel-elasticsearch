<?php

namespace Tests\TestCase\Integration\Traits;

trait HasDebugMode
{
    /**
     * @summery You can get you more detail about your test and operations that running about it for debugging reason!
     * @var bool
     */
    protected static bool $isDebugMode = false;
    protected static bool $isBootApplication = false;

    protected function printVerboseSetupDebugDetails(): void
    {
        if (self::$isDebugMode) {
            dump(PHP_EOL);
            dump(
                '******************************' . "Start Running Test " . $this->name(
                ) . ' *******************************'
            );
            dump(PHP_EOL);

            if (self::$isBootApplication) {
                dump('> setUp Application Called before Test Class! => ' . static::class);
                self::$isBootApplication = false;
            } else {
                dump('> setUp Method Called before method! => ' . $this->name());
            }
        }
    }

    protected function printVerboseOnNotSuccessfulTestDebugDetails(): void
    {
        if (self::$isDebugMode) {
            dump(sprintf("Test %s is failed", $this->name()));
        }
    }

    protected function printVerboseTearDownDebugDetails(): void
    {
        if (self::$isDebugMode) {

            $testName = $this->name();

            dump(sprintf(">> TearDown Called After Test => %s Run!", $testName));

            $message = '';

            if ($this->status()->isFailure()) {
                $message = sprintf("The test %s Failed!", $testName);
            }

            dump(PHP_EOL);
            dump('******************************' . $message . '*******************************');
            dump(PHP_EOL);
        }
    }

    protected static function printVerboseBootTestMigrationsDebugDetails(): void
    {
        if (self::$isDebugMode) {
            dump('<BootBeforeClass> SetupBeforeClass Called before class => ' . static::class);
            dump('<SettingUp Migrrations> migrations for elasticsearch setting up!');
        }
    }
}