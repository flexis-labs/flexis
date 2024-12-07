<?php

/**
 * Часть пакета Flexis Console Framework.
 */

namespace Flexis\Console\Descriptor;

use Flexis\Console\Application;
use Flexis\Console\Command\AbstractCommand;
use Flexis\String\StringHelper;
use Symfony\Component\Console\Descriptor\TextDescriptor as SymfonyTextDescriptor;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Дескриптор текстового объекта.
 */
final class TextDescriptor extends SymfonyTextDescriptor {
    /**
     * Описывает объект.
     *
     * @param   OutputInterface  $output   Выходной объект для использования.
     * @param   object           $object   Объект для описания.
     * @param   array            $options  Параметры дескриптора.
     *
     * @return  void
     */
    public function describe(OutputInterface $output, object $object, array $options = []): void {
        $this->output = $output;

        switch (true) {
            case $object instanceof Application:
                $this->describeFlexisApplication($object, $options);

                break;

            case $object instanceof AbstractCommand:
                $this->describeConsoleCommand($object, $options);

                break;

            default:
                parent::describe($output, $object, $options);
        }
    }

    /**
     * Форматирует псевдонимы команд, чтобы они отображались в описании команды.
     *
     * @param   AbstractCommand  $command  Команда для обработки
     *
     * @return  string
     */
    private function getCommandAliasesText(AbstractCommand $command): string {
        $text    = '';
        $aliases = $command->getAliases();

        if ($aliases) {
            $text = '[' . implode('|', $aliases) . '] ';
        }

        return $text;
    }

    /**
     * Описывает команду.
     *
     * @param   AbstractCommand  $command  Описываемая команда.
     * @param   array            $options  Параметры дескриптора.
     *
     * @return  void
     */
    private function describeConsoleCommand(AbstractCommand $command, array $options): void {
        $command->getSynopsis(true);
        $command->getSynopsis(false);
        $command->mergeApplicationDefinition(false);

        $this->writeText('<comment>Использование:</comment>', $options);

        foreach (array_merge([$command->getSynopsis(true)], $command->getAliases()) as $usage) {
            $this->writeText("\n");
            $this->writeText('  ' . $usage, $options);
        }

        $this->writeText("\n");

        $definition = $command->getDefinition();

        if ($definition->getOptions() || $definition->getArguments()) {
            $this->writeText("\n");
            $this->describeInputDefinition($definition, $options);
            $this->writeText("\n");
        }

        if ($help = $command->getProcessedHelp()) {
            $this->writeText("\n");
            $this->writeText('<comment>Помощь:</comment>', $options);
            $this->writeText("\n");
            $this->writeText('  ' . str_replace("\n", "\n  ", $help), $options);
            $this->writeText("\n");
        }
    }

    /**
     * Описывает приложение.
     *
     * @param   Application  $app      Описываемое приложение.
     * @param   array        $options  Параметры дескриптора.
     *
     * @return  void
     */
    private function describeFlexisApplication(Application $app, array $options): void {
        $describedNamespace = $options['namespace'] ?? '';
        $description        = new ApplicationDescription($app, $describedNamespace);

        $version = $app->getLongVersion();

        if ($version !== '') {
            $this->writeText("$version\n\n", $options);
        }

        $this->writeText("<comment>Использование:</comment>\n");
        $this->writeText("  command [options] [arguments]\n\n");

        $this->describeInputDefinition(new InputDefinition($app->getDefinition()->getOptions()), $options);

        $this->writeText("\n");
        $this->writeText("\n");

        $commands   = $description->getCommands();
        $namespaces = $description->getNamespaces();

        if ($describedNamespace && $namespaces) {
            $describedNamespaceInfo = reset($namespaces);

            foreach ($describedNamespaceInfo['commands'] as $name) {
                $commands[$name] = $description->getCommand($name);
            }
        }

        $width = $this->getColumnWidth(
            \call_user_func_array(
                'array_merge',
                array_map(
                    function ($namespace) use ($commands) {
                        return array_intersect($namespace['commands'], array_keys($commands));
                    },
                    array_values($namespaces)
                )
            )
        );

        if ($describedNamespace) {
            $this->writeText(sprintf('<comment>Доступные команды для пространства имен «%s»:</comment>', $describedNamespace), $options);
        } else {
            $this->writeText('<comment>Доступные команды:</comment>', $options);
        }

        foreach ($namespaces as $namespace) {
            $namespace['commands'] = array_filter(
                $namespace['commands'],
                function ($name) use ($commands) {
                    return isset($commands[$name]);
                }
            );

            if (!$namespace['commands']) {
                continue;
            }

            if (!$describedNamespace && $namespace['id'] !== ApplicationDescription::GLOBAL_NAMESPACE) {
                $this->writeText("\n");
                $this->writeText(' <comment>' . $namespace['id'] . '</comment>', $options);
            }

            foreach ($namespace['commands'] as $name) {
                $this->writeText("\n");
                $spacingWidth   = $width - StringHelper::strlen($name);
                $command        = $commands[$name];
                $commandAliases = $name === $command->getName() ? $this->getCommandAliasesText($command) : '';

                $this->writeText(
                    sprintf(
                        '  <info>%s</info>%s%s',
                        $name,
                        str_repeat(' ', $spacingWidth),
                        $commandAliases . $command->getDescription()
                    ),
                    $options
                );
            }
        }

        $this->writeText("\n");
    }

    /**
     * Рассчитывает ширину столбца для группы команд.
     *
     * @param   AbstractCommand[]|string[]  $commands    Команды, используемые для обработки ширины.
     *
     * @return  integer
     */
    private function getColumnWidth(array $commands): int {
        $widths = [];

        foreach ($commands as $command) {
            if ($command instanceof AbstractCommand) {
                $widths[] = StringHelper::strlen($command->getName());

                foreach ($command->getAliases() as $alias) {
                    $widths[] = StringHelper::strlen($alias);
                }
            } else {
                $widths[] = StringHelper::strlen($command);
            }
        }

        return $widths ? max($widths) + 2 : 0;
    }

    /**
     * Записывает текст на вывод.
     *
     * @param   string  $content  Сообщение.
     * @param   array   $options  Параметры, используемые для форматирования вывода.
     *
     * @return  void
     */
    private function writeText(string $content, array $options = []): void {
        $this->write(
            isset($options['raw_text']) && $options['raw_text'] ? strip_tags($content) : $content,
            isset($options['raw_output']) ? !$options['raw_output'] : true
        );
    }
}
