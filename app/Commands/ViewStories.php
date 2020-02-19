<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use InstagramAPI\Instagram;
use LaravelZero\Framework\Commands\Command;

class ViewStories extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'instagram:viewstories {account} {--username=} {--password=} ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Instagram :: Show Views Story';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        if (!env('INSTAGRAM_USERNAME') || !env('INSTAGRAM_PASSWORD')) {
            $this->error('Please provide your username and password in the .env file.');
            exit;
        }

        $ig = $this->login(env('INSTAGRAM_USERNAME'), env('INSTAGRAM_PASSWORD'));

        $account = $this->option('account');
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
    private function login(string $username, string $password): Instagram
    {
        $ig = new Instagram;

        $try      = 0;
        $loggedIn = false;

        do {
            if ($try == 5) {
                $this->error('The attempted login failed. Please try again later.');
                exit(1);
            }

            try {
                $loginResponse = $ig->login($username, $password);

                if ($loginResponse !== null && $loginResponse->isTwoFactorRequired()) {
                    if ($loginResponse->getTwoFactorInfo()->getTotpTwoFactorOn()) {
                        $message             = "Please enter your two-factor authentication code";
                        $twoFactorIdentifier = $loginResponse->getTwoFactorInfo()->getTwoFactorIdentifier();
                        $method              = '3';
                    } else {
                        $obfuscatedPhoneNumber = $loginResponse->getTwoFactorInfo()->getObfuscatedPhoneNumber();
                        $message               = "Please enter the two-factor authentication code sent to the number ending in $obfuscatedPhoneNumber";
                        $twoFactorIdentifier   = $loginResponse->getTwoFactorInfo()->getTwoFactorIdentifier();
                        $method                = '1';
                    }

                    $code = $this->ask($message);

                    if (empty($code)) {
                        do {
                            $code = $this->ask($message);
                        } while (empty($code));
                    }

                    $loggedIn = false;
                    $try      = 0;

                    do {
                        if ($try == 5) {
                            throw new Exception("The attempted login failed. Please try again later.");
                        }

                        try {
                            $loginResponse = $ig->finishTwoFactorLogin($username, $password, $twoFactorIdentifier, $code, $method);
                            $loggedIn      = true;
                        } catch (\InstagramAPI\Exception\InvalidSmsCodeException $e) {
                            $correctCode = false;
                            $loggedIn    = true;

                            do {
                                $code = $this->ask("Incorrect code. Please try again");

                                if (empty($code)) {
                                    do {
                                        $code = $this->ask("Please try again");
                                    } while (empty($code));
                                }

                                $loggedIn = false;
                                $try      = 0;

                                do {
                                    try {
                                        if ($try == 5) {
                                            $this->error('The attempted login failed. Please try again later.');
                                            exit(1);
                                        }
                                        $loginResponse = $ig->finishTwoFactorLogin($login, $password, $twoFactorIdentifier, $code, $method);
                                        $correctCode   = true;
                                        $loggedIn      = true;
                                    } catch (\InstagramAPI\Exception\InvalidSmsCodeException $e) {
                                        $correctCode = false;
                                        $loggedIn    = true;
                                    } catch (\Exception $e) {
                                        throw $e;
                                    }
                                    $try += 1;
                                } while (!$loggedIn);
                            } while (!$correctCode);
                        } catch (\Exception $e) {
                            throw $e;
                        }
                        $try += 1;
                    } while (!$loggedIn);
                }
                $loggedIn = true;
            } catch (\InstagramAPI\Exception\IncorrectPasswordException $e) {
                $this->error('The password you provided is incorrect. Please try again.');
                exit(1);
            } catch (\InstagramAPI\Exception\ThrottledException $e) {
                $this->comment('Throttled by Instagram because of too many API requests.');
                sleep(5);
            } catch (\Exception $e) {
                throw $e;
            }
            $try += 1;
        } while (!$loggedIn);

        return $ig;
    }
}
