<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use App\Service\MoneyParser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MoneyParser $moneyParser,
    ) {
        parent::__construct();
        ini_set('memory_limit', '1024M');
    }

    protected function configure(): void
    {
        $this
            ->setName('app:import')
            ->setDescription('Import a strichliste 1 SQLite database (replaces all current data)')
            ->addArgument('database', InputArgument::REQUIRED, 'SQLite database file from strichliste 1')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Required to wipe and replace a target database that already holds data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $databaseFile = $input->getArgument('database');

        $config = new \Doctrine\DBAL\Configuration();
        $connection = DriverManager::getConnection([
            'url' => sprintf('sqlite:///%s', $databaseFile),
        ], $config);

        // DBAL 4 connects lazily on first query; the explicit connect() call was removed.
        $entityManager = $this->entityManager;

        // This importer is for strichliste 1 only and starts by wiping the target.
        // If the target already holds data (e.g. a strichliste 2 install someone is
        // trying to "import into"), refuse unless --force so we never silently delete
        // existing balances. To reuse an existing strichliste 2 database, point
        // DATABASE_URL at it and boot instead — the migrations run safely on it.
        $existingUsers = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->getQuery()
            ->getSingleScalarResult();

        if ($existingUsers > 0 && !$input->getOption('force')) {
            $output->writeln(sprintf('<error>The target database already contains %d user(s).</error>', $existingUsers));
            $output->writeln('app:import wipes all users, transactions and articles before importing.');
            $output->writeln('Re-run with <info>--force</info> if you really want to replace this data.');
            $output->writeln('(To keep an existing strichliste 2 database, set DATABASE_URL to it and start the app — no import needed.)');

            return Command::FAILURE;
        }

        $sourceTransactions = $this->querySourceTransactions($connection);
        foreach ($sourceTransactions->iterateAssociative() as $transaction) {
            if (null === $this->moneyParser->parseToMinorUnits($transaction['value'])) {
                $output->writeln('<error>Cannot import a fractional or invalid amount with the configured currency precision.</error>');

                return Command::FAILURE;
            }
        }

        $entityManager->createQueryBuilder()->delete(Transaction::class)->getQuery()->execute();
        $entityManager->createQueryBuilder()->delete(User::class)->getQuery()->execute();
        $entityManager->createQueryBuilder()->delete(Article::class)->getQuery()->execute();

        try {
            $result = $connection->executeQuery('select id, name, mailAddress, createDate from users');
        } catch (\Exception) {
            $result = $connection->executeQuery("select id, name, '' as mailAddress, createDate from users");
        }

        $userMapping = [];
        foreach ($result->iterateAssociative() as $user) {
            $id = (int) $user['id'];
            $name = $user['name'];

            // Just in case there is a dub from strichliste1, append id
            if ($entityManager->getRepository(User::class)->findByName($name)) {
                $output->writeln(sprintf("WARNING: User '%s' (%d) has been renamed to '%s%02d' due to unique constrain", $name, $id, $name, $id));
                $name = sprintf('%s%02d', $name, $id);
            }

            $newUser = new User();
            $newUser->setName($name);
            $newUser->setCreated(new \DateTime($user['createDate']));

            if ($user['mailAddress']) {
                $newUser->setEmail($user['mailAddress']);
            }

            $entityManager->persist($newUser);
            $entityManager->flush();

            $output->writeln(sprintf("Imported user '%s'", $newUser->getName()));

            $userMapping[$id] = $newUser;
        }

        $result = $this->querySourceTransactions($connection);

        $count = 0;
        foreach ($result->iterateAssociative() as $transaction) {
            $userId = (int) $transaction['userId'];
            $user = $userMapping[$userId];

            $newTransaction = new Transaction();
            $newTransaction->setUser($user);
            $newTransaction->setAmount($this->moneyParser->parseToMinorUnits($transaction['value'])
                ?? throw new \LogicException('A prevalidated legacy amount became invalid.'));
            $newTransaction->setCreated(new \DateTime($transaction['createDate']));

            if ($transaction['comment']) {
                $newTransaction->setComment($transaction['comment']);
            }

            $entityManager->persist($newTransaction);
            ++$count;
        }

        $entityManager->flush();
        $output->writeln(sprintf('Imported %d transactions', $count));

        /**
         * @var User $user
         */
        foreach ($userMapping as $user) {
            $result = $entityManager->createQueryBuilder()
                ->select('SUM(t.amount) as amount, MAX(t.created) as latestTransaction')
                ->from(Transaction::class, 't')
                ->where('t.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleResult();

            $amount = (int) $result['amount'];
            $latestTransaction = $result['latestTransaction'];
            if ($amount) {
                $user->setBalance($amount);

                $displayAmount = number_format(
                    $this->moneyParser->minorToMajorUnits($amount),
                    $this->moneyParser->getFractionDigits(),
                    '.',
                    ',',
                );
                $output->writeln(sprintf("Update balance of user '%s' to %s", $user->getName(), $displayAmount));

                if ($latestTransaction) {
                    $user->setUpdated(new \DateTime($latestTransaction));
                }

                $entityManager->persist($user);
            }
        }

        $entityManager->flush();
        $output->writeln('Import done!');

        return Command::SUCCESS;
    }

    private function querySourceTransactions(Connection $connection): Result
    {
        try {
            return $connection->executeQuery('select t.userId, value, t.comment, t.createDate from transactions as t join users on users.id = t.userId');
        } catch (\Exception) {
            return $connection->executeQuery("select t.userId, value, '' as comment, t.createDate from transactions as t join users on users.id = t.userId");
        }
    }
}
