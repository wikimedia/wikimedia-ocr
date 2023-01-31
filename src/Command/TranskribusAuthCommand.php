<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TranskribusAuthCommand extends Command
{

    private $client;

    public function __construct(HttpClientInterface $client)
    {
        parent::__construct();
        $this->client = $client;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to retrieve and store an access token for testing the Trankribus engine');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $io = new SymfonyStyle($input, $output);

        $userNameQuestion = new Question('Enter username: ');
        $userName = $helper->ask($input, $output, $userNameQuestion);

        $passwordQuestion = new Question('Enter password: ');
        $password = $helper->ask($input, $output, $passwordQuestion);

        $clientId = $_ENV['CLIENT_ID'];
        
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
                                'client_id' => $clientId,
                                'scope' => 'offline_access'
                            ]
                        ]
                    );    
        $statusCode = $token_response->getStatusCode();
        if ( $statusCode == 200 ) {
            $content = json_decode($token_response->getContent());
            $access_token = $content->{'access_token'};
            $output->writeln([
                'Your access token is: '.$access_token,
                $io->newline(),
                '--- Please copy and store the access token for future use ---'
            ]);
            return Command::SUCCESS;
        } else if ($statusCode == 401) {
            $output->writeln('Error Code '.$statusCode.' :: Credentials are incorrect!');
            return Command::FAILURE;
        } else {
            $output->writeln('Error Code '.$statusCode.' :: Error generating access_token, try again!');
            return Command::FAILURE;
        }
    }
}