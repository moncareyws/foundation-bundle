<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MoncareyWS\FoundationBundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Security\InteractiveSecurityHelper;
use Symfony\Bundle\MakerBundle\Security\SecurityConfigUpdater;
use Symfony\Bundle\MakerBundle\Security\SecurityControllerBuilder;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassSourceManipulator;
use Symfony\Bundle\MakerBundle\Util\YamlManipulationFailedException;
use Symfony\Bundle\MakerBundle\Util\YamlSourceManipulator;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;
use MoncareyWS\FoundationBundle\Generator\Generator;
use MoncareyWS\FoundationBundle\Command\FoundationCommand as Command;


class FoundationAuthenticator extends AbstractFoundationMaker
{
    const AUTH_TYPE_EMPTY_AUTHENTICATOR = 'empty-authenticator';
    const AUTH_TYPE_FORM_LOGIN = 'form-login';

    private $fileManager;

    private $configUpdater;

    private $generator;

    private $doctrineHelper;

    public function __construct(FileManager $fileManager, SecurityConfigUpdater $configUpdater, Generator $generator, DoctrineHelper $doctrineHelper)
    {
        $this->fileManager = $fileManager;
        $this->configUpdater = $configUpdater;
        $this->generator = $generator;
        $this->doctrineHelper = $doctrineHelper;
    }

    public static function getCommandName(): string
    {
        return 'foundation:make:auth';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig)
    {
        $command
            ->setDescription('Creates a Guard authenticator of different flavors')
            ->setHelp(file_get_contents(__DIR__.'/../Resources/help/FoundationAuth.txt'));
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
    {
        if (!$this->fileManager->fileExists($path = 'config/packages/security.yaml')) {
            throw new RuntimeCommandException('The file "config/packages/security.yaml" does not exist. This command requires that file to exist so that it can be updated.');
        }
        $manipulator = new YamlSourceManipulator($this->fileManager->getFileContents($path));
        $securityData = $manipulator->getData();

        // authenticator class
        $command->addArgument('authenticator-class', InputArgument::REQUIRED);
        $questionAuthenticatorClass = new Question('The class name of the authenticator to create (e.g. <fg=yellow>AppCustomAuthenticator</>)');
        $questionAuthenticatorClass->setValidator(
            function ($answer) {
                Validator::notBlank($answer);

                return Validator::classDoesNotExist(
                    $this->generator->createClassNameDetails($answer, 'Security\\', 'Authenticator')->getFullName()
                );
            }
        );
        $input->setArgument('authenticator-class', $io->askQuestion($questionAuthenticatorClass));

        $interactiveSecurityHelper = new InteractiveSecurityHelper();
        $command->addOption('firewall-name', null, InputOption::VALUE_OPTIONAL);
        $input->setOption('firewall-name', $firewallName = $interactiveSecurityHelper->guessFirewallName($io, $securityData));

        $command->addOption('entry-point', null, InputOption::VALUE_OPTIONAL);
        $input->setOption(
            'entry-point',
            $interactiveSecurityHelper->guessEntryPoint($io, $securityData, $input->getArgument('authenticator-class'), $firewallName)
        );

        $command->addArgument('controller-class', InputArgument::REQUIRED);
        $input->setArgument(
            'controller-class',
            $io->ask(
                'Choose a name for the controller class (e.g. <fg=yellow>SecurityController</>)',
                'SecurityController',
                [Validator::class, 'validateClassName']
            )
        );

        $command->addArgument('user-class', InputArgument::REQUIRED);
        $input->setArgument(
            'user-class',
            $userClass = $interactiveSecurityHelper->guessUserClass($io, $securityData['security']['providers'])
        );

        $command->addArgument('username-field', InputArgument::REQUIRED);
        $input->setArgument(
            'username-field',
            $interactiveSecurityHelper->guessUserNameField($io, $userClass, $securityData['security']['providers'])
        );
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator)
    {
        $manipulator = new YamlSourceManipulator($this->fileManager->getFileContents('config/packages/security.yaml'));
        $securityData = $manipulator->getData();

        $this->generateAuthenticatorClass(
            $securityData,
            $input->getArgument('authenticator-class'),
            $input->hasArgument('user-class') ? $input->getArgument('user-class') : null,
            $input->hasArgument('username-field') ? $input->getArgument('username-field') : null
        );

        // update security.yaml with guard config
        $securityYamlUpdated = false;
        try {
            $newYaml = $this->configUpdater->updateForAuthenticator(
                $this->fileManager->getFileContents($path = 'config/packages/security.yaml'),
                $input->getOption('firewall-name'),
                $input->getOption('entry-point'),
                $input->getArgument('authenticator-class')
            );
            $generator->dumpFile($path, $newYaml);
            $securityYamlUpdated = true;
        } catch (YamlManipulationFailedException $e) {
        }

        $this->generateFormLoginFiles($input->getArgument('controller-class'), $input->getArgument('username-field'));

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text(
            $this->generateNextMessage(
                $securityYamlUpdated,
                $input->getArgument('authenticator-class'),
                $securityData,
                $input->hasArgument('user-class') ? $input->getArgument('user-class') : null
            )
        );
    }

    private function generateAuthenticatorClass(array $securityData, string $authenticatorClass, $userClass, $userNameField)
    {
        // generate authenticator class
        $userClassNameDetails = $this->generator->createClassNameDetails(
            '\\'.$userClass,
            'Entity\\'
        );

        $this->generator->generateClass(
            $authenticatorClass,
            'authenticator/LoginFormAuthenticator.php.twig',
            [
                'user_fully_qualified_class_name' => trim($userClassNameDetails->getFullName(), '\\'),
                'user_class_name' => $userClassNameDetails->getShortName(),
                'username_field' => $userNameField,
                'username_field_label' => Str::asHumanWords($userNameField),
                'user_needs_encoder' => $this->userClassHasEncoder($securityData, $userClass),
                'user_is_entity' => $this->doctrineHelper->isClassAMappedEntity($userClass),
            ]
        );
    }

    private function generateFormLoginFiles(string $controllerClass, string $userNameField)
    {
        $controllerClassNameDetails = $this->generator->createClassNameDetails(
            $controllerClass,
            'Controller\\',
            'Controller'
        );

        if (!class_exists($controllerClassNameDetails->getFullName())) {
            $controllerPath = $this->generator->generateController(
                $controllerClassNameDetails->getFullName(),
                'authenticator/EmptySecurityController.php.twig'
            );

            $controllerSourceCode = $this->generator->getFileContentsForPendingOperation($controllerPath);
        } else {
            $controllerPath = $this->fileManager->getRelativePathForFutureClass($controllerClassNameDetails->getFullName());
            $controllerSourceCode = $this->fileManager->getFileContents($controllerPath);
        }

        if (method_exists($controllerClassNameDetails->getFullName(), 'login')) {
            throw new RuntimeCommandException(sprintf('Method "login" already exists on class %s', $controllerClassNameDetails->getFullName()));
        }

        $manipulator = new ClassSourceManipulator($controllerSourceCode, true);

        $securityControllerBuilder = new SecurityControllerBuilder();
        $securityControllerBuilder->addLoginMethod($manipulator);

        $this->generator->dumpFile($controllerPath, $manipulator->getSourceCode());

        // create login form template
        $this->generator->generateFile(
            'templates/security/login.html.twig',
            'authenticator/login.html.twig.twig',
            [
                'username_field' => $userNameField,
                'username_is_email' => false !== stripos($userNameField, 'email'),
                'username_label' => ucfirst(Str::asHumanWords($userNameField)),
            ]
        );
    }

    private function generateNextMessage(bool $securityYamlUpdated, string $authenticatorClass, array $securityData, $userClass): array
    {
        $nextTexts = ['Next:'];
        $nextTexts[] = '- Customize your new authenticator.';

        if (!$securityYamlUpdated) {
            $yamlExample = $this->configUpdater->updateForAuthenticator(
                'security: {}',
                'main',
                null,
                $authenticatorClass
            );
            $nextTexts[] = '- Your <info>security.yaml</info> could not be updated automatically. You\'ll need to add the following config manually:\n\n'.$yamlExample;
        }

        $nextTexts[] = sprintf('- Finish the redirect "TODO" in the <info>%s::onAuthenticationSuccess()</info> method.', $authenticatorClass);

        if (!$this->doctrineHelper->isClassAMappedEntity($userClass)) {
            $nextTexts[] = sprintf('- Review <info>%s::getUser()</info> to make sure it matches your needs.', $authenticatorClass);
        }

        if (!$this->userClassHasEncoder($securityData, $userClass)) {
            $nextTexts[] = sprintf('- Check the user\'s password in <info>%s::checkCredentials()</info>.', $authenticatorClass);
        }

        $nextTexts[] = '- Review & adapt the login template: <info>templates/security/login.html.twig</info>.';

        return $nextTexts;
    }

    private function userClassHasEncoder(array $securityData, string $userClass): bool
    {
        $userNeedsEncoder = false;
        if (isset($securityData['security']['encoders']) && $securityData['security']['encoders']) {
            foreach ($securityData['security']['encoders'] as $userClassWithEncoder => $encoder) {
                if ($userClass === $userClassWithEncoder || is_subclass_of($userClass, $userClassWithEncoder)) {
                    $userNeedsEncoder = true;
                }
            }
        }

        return $userNeedsEncoder;
    }

    public function configureDependencies(DependencyBuilder $dependencies, InputInterface $input = null)
    {
        $dependencies->addClassDependency(
            SecurityBundle::class,
            'security'
        );

        // needed to update the YAML files
        $dependencies->addClassDependency(
            Yaml::class,
            'yaml'
        );

        // needed to update the YAML files
        $dependencies->addClassDependency(
            TwigBundle::class,
            'twig'
        );
    }
}
