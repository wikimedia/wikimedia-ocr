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

    public function __construct(HttpClientInterface $client, string $clientId)
    {
        parent::__construct();
        $this->client = $client;
        $this->clientId = $clientId;
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
        $password = $helper->ask($input, $output, $passwordQuestion);

        $token_response = $this->client
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
                                'client_id' => $this->clientId,
                                'scope' => 'offline_access',
                            ],
                        ]
                    );
        $statusCode = $token_response->getStatusCode();
        if (200 === $statusCode) {
            $content = json_decode($token_response->getContent());
            if (is_null($content)) {
                $output->writeln('Transkribus API returned null');
                return Command::FAILURE;
            } else {
                $access_token = $content->{'access_token'};
                $output->writeln([
                    'Your access token is: '.$access_token,
                    $io->newline(),
                    '--- Please copy and add it to your .env.local as APP_TRANSKRIBUS_ACCESS_TOKEN ---',
                ]);
                return Command::SUCCESS;
            }
        } elseif (401 === $statusCode) {
            $output->writeln('Error Code '.$statusCode.' :: Credentials are incorrect!');
            return Command::FAILURE;
        } else {
            $output->writeln('Error Code '.$statusCode.' :: Error generating access_token, try again!');
            return Command::FAILURE;
        }
    }
}
