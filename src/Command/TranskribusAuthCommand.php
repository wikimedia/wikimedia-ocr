<?php declare(strict_types = 1);

namespace App\Command;

use App\Engine\TranskribusClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranskribusAuthCommand extends Command
{

    /** @var TranskribusClient The TranskribusClient used to make authorization calls */
    private $transkribusClient;

    /**
     * TranskribusAuthCommand constructor.
     * @param TranskribusClient $transkribusClient
     */
    public function __construct(
        TranskribusClient $transkribusClient
    ) {
        parent::__construct();

        $this->transkribusClient = $transkribusClient;
    }

    protected function configure(): void
    {
        $this
            ->setName('app:transkribus')
            ->setDescription('This command allows you to retrieve and store an 
                access token and a refresh token for the Trankribus API');
    }

    /**
     * Method that runs the CLI command.
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $io = new SymfonyStyle($input, $output);

        $userNameQuestion = new Question('Enter username: ');
        $userName = $helper->ask($input, $output, $userNameQuestion);

        $passwordQuestion = new Question('Enter password: ');
        $passwordQuestion->setHidden(true);
        $password = $helper->ask($input, $output, $passwordQuestion);

        $tokenResponse = $this->transkribusClient->getAccessTokenResponse($userName, $password);

        $statusCode = $tokenResponse->getStatusCode();
        if (200 === $statusCode) {
            $content = json_decode($tokenResponse->getContent());
            if (is_null($content)) {
                $io->error('Response from Transkribus API could not be parsed');
                return Command::FAILURE;
            } else {
                $refreshToken = $content->{'refresh_token'};
                $accessToken = $content->{'access_token'};
                $io->writeln('');
                $io->writeln('--- Please copy the following lines and add it to your .env.local ---');
                $io->writeln('');
                $io->writeln('APP_TRANSKRIBUS_ACCESS_TOKEN='.$accessToken);
                $io->writeln('');
                $io->writeln('APP_TRANSKRIBUS_REFRESH_TOKEN='.$refreshToken);
                return Command::SUCCESS;
            }
        } elseif (401 === $statusCode) {
            $io->error('Error Code '.$statusCode.' :: Credentials are incorrect!');
            return Command::FAILURE;
        } else {
            $io->error('Error Code '.$statusCode.' :: Error generating access token/refresh token, try again!');
            return Command::FAILURE;
        }
    }
}
