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
    protected $signature = 'instagram:viewstories {--username=} {--password=} {--like=false} ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Instagram :: Show Views Story';
    protected $ig;
    protected $instagram_login;
    protected $instagram_password;
    protected $like;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        // $this->line(print_r(app()));
        if (!env('INSTAGRAM_USERNAME') || !env('INSTAGRAM_PASSWORD')) {
            $this->error('Please provide your username and password in the .env file.');
            exit;
        }
        $this->line("--------------------------------------------------------------------");
        $this->line("--------------------------------------------------------------------");
        $this->line("--------------------------------------------------------------------");
        $this->line("------------------------- Instagram Cli ----------------------------");
        $this->line("--------------------------------------------------------------------");
        $this->line("--------------------------------------------------------------------");
        $this->line("--------------------------------------------------------------------");

        $this->instagram_login    = ($this->option('username')) ? $this->option('username') : env('INSTAGRAM_USERNAME');
        $this->instagram_password = ($this->option('password')) ? $this->option('password') : env('INSTAGRAM_PASSWORD');
        $this->like               = $this->option('password');
        $this->line("--------------------------- pre Login ------------------------------");
        $this->ig = $this->login($this->instagram_login, $this->instagram_password);
        $this->line("-------------------------- Login Done ------------------------------");
        $this->line("------------------------ start get mark ----------------------------");
        $result = $this->getmarkMediaSeen();
        $this->line("------------------------ finish get mark ---------------------------");
        if ($this->like == true) {
            $this->getLikes($result);
        }

    }
    public function getLikes($users = [])
    {
        $this->line("------------------------ start Likes ---------------------------");

        $bar = $this->output->createProgressBar(count($users));
        $bar->start();
        foreach ($users as $user) {
            $items = $this->ig->timeline->getUserFeed($user);
            $line  = 1;

            foreach ($items->getItems() as $item) {

                if ($line > 1) {
                    continue;
                }
                // $this->line("Code: " . $item->getCode());
                // $this->line("Username: " . $item->getUser()->getUsername());
                $liked = $this->ig->media->like($item->getId(), $line);
                // $this->line("Like: " . $liked->getStatus());
                $line++;
                sleep(rand(3, 5));
            }
            $bar->advance();
        }
        $bar->finish();
        $this->line("------------------------ start Likes ---------------------------");

    }

    public function getmarkMediaSeen()
    {
        try {

            $this->line("--------------------------------------------------------------------");
            $this->line("--------------------------------------------------------------------");
            $this->line("--------------------------------------------------------------------");
            $this->line("------------------------- Mark Media Seen --------------------------");
            $this->line("--------------------------------------------------------------------");
            $this->line("--------------------------------------------------------------------");
            $this->line("--------------------------------------------------------------------");
            $users    = [];
            $feed     = $this->ig->story->getReelsTrayFeed();
            $itemsnew = $feed->getTray();
            $bar      = $this->output->createProgressBar(count($itemsnew));
            $bar->start();

            foreach ($itemsnew as $key => $item) {
                if ($key == 0) {continue;}
                // if ($key > 5) {continue;}
                if (is_null($item->getUser())) {continue;}
                $username = $item->getUser()->getUsername();
                $userID   = $this->ig->people->getUserIdForName($item->getUser()->getUsername());
                $feed3    = $this->ig->story->getUserStoryFeed($userID);
                $users[]  = $userID;
                if (is_null($feed3->getReel())) {
                    continue;
                }
                $items = $feed3->getReel()->getItems();
                $this->notify("instagram", "username: " . $username . " --- count: " . count($items));
                $this->ig->story->markMediaSeen($items);
                sleep(rand(3, 5));
                $bar->advance();
            }
            $bar->finish();
            $this->line("   ");
            $this->line("--------------------------------------------------------------------");
            return $users;
            // exit(0);

        } catch (\InstagramAPI\Exception\InstagramException $e) {
            echo 'Something went wrong: ' . $e->getMessage() . "\n";
        }
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
