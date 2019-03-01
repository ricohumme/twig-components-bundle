<?php

namespace RedAnt\TwigComponentsBundle\Command;

use RedAnt\TwigComponents\NodeVisitor\ComponentNodeVisitor;
use RedAnt\TwigComponents\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateDocsCommand extends Command
{
    protected static $defaultName = 'twig:components:generate-docs';

    /**
     * @var array
     */
    protected $templates;

    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var string
     */
    protected $global;

    public function __construct(Registry $componentRegistry, \Twig_Environment $twig, string $globalVariable)
    {
        parent::__construct(static::$defaultName);
        $this->templates = $componentRegistry->getComponents();
        $this->twig = $twig;
        $this->global = $globalVariable;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Generate documentation for Twig components')
            ->addArgument('path', InputArgument::REQUIRED,
                'Output directory')
            ->addOption('title', 't', InputOption::VALUE_REQUIRED,
                'Title for the generated documentation', 'Twig components')
            ->addOption('generic', 'g', InputOption::VALUE_NONE,
                'Disregard twig_component.global_variable settings and only show render_component() examples');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $path = rtrim($input->getArgument('path'), "/") . '/';
        $title = $input->getOption('title');

        if (!is_dir($path)) {
            $io->error("Path $path could not be found.");
        } else {
            if (!is_dir($path . 'components')) {
                mkdir($path . 'components');
            }
        }

        $definitions = $this->getDefinitionsFromTemplates();

        if (!file_exists("$path/README.md")) {
            copy(__DIR__ . '/../README.md', "$path/README.md");
        }

        foreach ([ 'index.html.twig', '_sidebar.md.twig' ] as $template) {
            file_put_contents($path . substr($template, 0, -5),
                $this->twig->render("@RedAntTwigComponents/$template", [
                    'title'      => $title,
                    'components' => array_keys($definitions),
                ]));
        }

        foreach ($definitions as $component => $definition) {
            file_put_contents("$path/components/$component.md",
                $this->twig->render('@RedAntTwigComponents/component.md.twig', [
                    'component'  => $component,
                    'definition' => $definition,
                    'template'   => current($this->templates),
                    'global'     => ($input->getOption('generic')) ? false : $this->global
                ]));
        }
    }

    /**
     * @return array
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    protected function getDefinitionsFromTemplates(): array
    {
        $visitor = new ComponentNodeVisitor();
        $this->twig->addNodeVisitor($visitor);
        $this->twig->addExtension(new \Twig_Extension_StringLoader());

        /**
         * Parse all component templates.
         */
        foreach ($this->templates as $name => $template) {
            $source = $this->twig->load($template)->getSourceContext();
            $tokens = $this->twig->tokenize($source);
            $this->twig->parse($tokens);
        }

        $definitions = $visitor->getDefinitions();
        ksort($definitions);

        return $definitions;
    }
}