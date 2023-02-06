<?php declare(strict_types = 1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranskribusAuthCommand extends Command
{   

    /** @var HttpClientInterface The interface to make http requests */
    private $client;

    /** @var string The client ID passed to the transkribus API */
    private $clientId;

    public function __construct(HttpClientInterface $client)
    {
        parent::__construct();
        $this->client = $client;
    }

    protected function configure(): void
    {
        $this
            ->setName('app:transkribus')
            ->setDescription('This command allows you to retrieve and store an access token for the Trankribus API');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $io = new SymfonyStyle($input, $output);

        $userNameQuestion = new Question('Enter username: ');
        $userName = $helper->ask($input, $output, $userNameQuestion);

        $passwordQuestion = new Question('Enter password: ');
        $passwordQuestion->setHidden(true);
        $password = $helper->ask($input, $output, $passwordQuestion);

        $tokenResponse = $this->client
                    ->request(
                        'POST',
                        'https://account.readcoop.eu/auth/realms/readcoop/protocol/openid-connect/token',
                        [
                            'headers' => [
                                'Content-Type' => 'application/x-www-form-urlencoded',
                            ],
                            'body' => [
                                'grant_type' => 'password',
                                'username' => $userName,
                                'password' => $password,
                                'client_id' => 'processing-api-client',
                                'scope' => 'offline_access',
                            ],
                        ]
                    );
        $statusCode = $tokenResponse->getStatusCode();
        if (200 === $statusCode) {
            $content = json_decode($tokenResponse->getContent());
            if (is_null($content)) {
                $io->error('Response from Transkribus API could not be parsed');
                return Command::FAILURE;
            } else {
                $refreshToken = $content->{'refresh_token'};
                $tokenResponse = $this->client
                    ->request(
                        'POST',
                        'https://account.readcoop.eu/auth/realms/readcoop/protocol/openid-connect/token',
                        [
                            'headers' => [
                                'Content-Type' => 'application/x-www-form-urlencoded',
                            ],
                            'body' => [
                                'grant_type' => 'refresh_token',
                                'client_id' => 'processing-api-client',
                                'refresh_token' => $refreshToken,
                            ],
                        ]
                    );
                $statusCode = $tokenResponse->getStatusCode();
                if (200 === $statusCode) {
                    $content = json_decode($tokenResponse->getContent());
                    if (is_null($content)) {
                        $io->error('Response from Transkribus API could not be parsed');
                        return Command::FAILURE;
                    } else {
                        $accessToken = $content->{'access_token'};
                        $io->writeln('');
                        $io->writeln('Your access token is: '.$accessToken);
                        $io->writeln('');
                        $io->writeln('--- Please copy and add it to your .env.local'.
                                                ' as APP_TRANSKRIBUS_ACCESS_TOKEN ---');
                        return Command::SUCCESS;
                    }
                } elseif (400 === $statusCode) {
                    $io->error('Error Code '.$statusCode.' :: Refresh token is incorrect');
                    return Command::FAILURE;
                } else {
                    $io->error('Error Code '.$statusCode.' :: Error generating access'.
                                                ' token from refresh token, try again!');
                    return Command::FAILURE;
                }
            }
        } elseif (401 === $statusCode) {
            $io->error('Error Code '.$statusCode.' :: Credentials are incorrect!');
            return Command::FAILURE;
        } else {
            $io->error('Error Code '.$statusCode.' :: Error generating refresh token, try again!');
            return Command::FAILURE;
        }
    }
}
