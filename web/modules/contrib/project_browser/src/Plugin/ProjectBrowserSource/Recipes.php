<?php

declare(strict_types=1);

namespace Drupal\project_browser\Plugin\ProjectBrowserSource;

use Composer\InstalledVersions;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\project_browser\Attribute\ProjectBrowserSource;
use Drupal\project_browser\Plugin\ProjectBrowserSourceBase;
use Drupal\project_browser\ProjectBrowser\Filter\TextFilter;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;
use Drupal\project_browser\ProjectType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;

/**
 * A source plugin that exposes recipes installed locally.
 *
 * @internal
 *   This is an internal part of Project Browser and may be changed or removed
 *   at any time. It should not be used by external code.
 */
#[ProjectBrowserSource(
  id: 'recipes',
  label: new TranslatableMarkup('Recipes'),
  description: new TranslatableMarkup('Recipes available in the local code base'),
  local_task: [
    'weight' => 2,
  ]
)]
final class Recipes extends ProjectBrowserSourceBase {

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly CacheBackendInterface $cacheBin,
    private readonly ModuleExtensionList $moduleList,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly string $appRoot,
    mixed ...$arguments,
  ) {
    parent::__construct(...$arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    assert(is_string($container->getParameter('app.root')));
    return new static(
      $container->get(FileSystemInterface::class),
      $container->get('cache.project_browser'),
      $container->get(ModuleExtensionList::class),
      $container->get(ConfigFactoryInterface::class),
      $container->getParameter('app.root'),
      ...array_slice(func_get_args(), 1),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterDefinitions(): array {
    return [
      'search' => new TextFilter('', $this->t('Search')),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects(array $query = []): ProjectsResultsPage {
    $cached = $this->cacheBin->get($this->getPluginId());
    if ($cached) {
      $projects = $cached->data;
    }
    else {
      $projects = [];

      $logo_uri = 'base:/' . $this->moduleList->getPath('project_browser') . '/images/recipe-logo.png';

      $finder = $this->getFinder();
      // If we're in a test environment, scan for our test recipes too, along
      // with any arbitrary places that might be specified in a setting.
      if (Settings::get('extension_discovery_scan_tests', FALSE) || drupal_valid_test_ua()) {
        $finder->in([
          ...Settings::get('project_browser_recipe_directories', []),
          __DIR__ . '/../../../tests/fixtures',
        ]);
      }
      /** @var \Symfony\Component\Finder\SplFileInfo $file */
      foreach ($finder as $file) {
        $path = $file->getPath();

        // If the recipe isn't part of Drupal core, get its package name from
        // `composer.json`. This shouldn't be necessary once drupal.org has a
        // proper API endpoint that provides project information for recipes.
        if (str_starts_with($path, $this->appRoot . '/core/recipes/')) {
          $package_name = 'drupal/core';
        }
        else {
          $package = file_get_contents($path . '/composer.json');
          assert(is_string($package));
          $package = Json::decode($package);
          $package_name = $package['name'];

          if (array_key_exists('homepage', $package)) {
            $url = Url::fromUri($package['homepage']);
          }
        }

        $recipe = Yaml::decode($file->getContents());
        $description = $recipe['description'] ?? NULL;

        $projects[] = new Project(
          logo: Url::fromUri($logo_uri),
          isCompatible: TRUE,
          machineName: basename($path),
          body: $description ? ['summary' => $description] : [],
          title: $recipe['name'],
          packageName: $package_name,
          type: ProjectType::Recipe,
          url: $url ?? NULL,
        );
      }
      // Sort the $projects array by the 'title' property in ascending order.
      usort($projects, function (Project $a, Project $b) {
        return strcasecmp($a->title, $b->title);
      });
      $this->cacheBin->set($this->getPluginId(), $projects);
    }

    // Filter by project machine name.
    if (!empty($query['machine_name'])) {
      $projects = array_filter($projects, fn(Project $project): bool => $project->machineName === $query['machine_name']);
    }

    // Filter by coverage.
    if (!empty($query['security_advisory_coverage'])) {
      $projects = array_filter($projects, fn(Project $project): bool => $project->isCovered ?? FALSE);
    }

    // Filter by categories.
    if (!empty($query['categories'])) {
      $projects = array_filter($projects, fn(Project $project): bool => empty(array_intersect(array_column($project->categories, 'id'), explode(',', $query['categories']))));
    }

    // Filter by search text.
    if (!empty($query['search'])) {
      $projects = array_filter($projects, fn(Project $project): bool => stripos($project->title, $query['search']) !== FALSE);
    }

    $total = count($projects);

    // Filter by sorting criterion.
    if (!empty($query['sort'])) {
      $sort = $query['sort'];
      switch ($sort) {
        case 'a_z':
          usort($projects, fn($x, $y) => $x->title <=> $y->title);
          break;

        case 'z_a':
          usort($projects, fn($x, $y) => $y->title <=> $x->title);
          break;
      }
    }

    if (array_key_exists('page', $query) && !empty($query['limit'])) {
      $projects = array_chunk($projects, $query['limit'])[$query['page']] ?? [];
    }

    if (array_key_exists('order', $this->configuration)) {
      SortHelper::sortInDefinedOrder($projects, $this->configuration['order']);
    }
    return $this->createResultsPage($projects, $total);
  }

  /**
   * Prepares a Symfony Finder to search for recipes in the file system.
   *
   * @return \Symfony\Component\Finder\Finder
   *   A Symfony Finder object, configured to find locally installed recipes.
   */
  private function getFinder(): Finder {
    $search_in = [$this->appRoot . '/core/recipes'];

    // Search wherever Composer is configured to install recipes. The recipe
    // system requires that all non-core recipes be located next to each other,
    // in the same directory.
    $recipes_dir = static::getRecipesPath();
    if ($recipes_dir) {
      // Handle the most common case, where the recipe name is the last part
      // of the path.
      if (basename($recipes_dir) === '{$name}') {
        $recipes_dir = dirname($recipes_dir);
      }
      $recipes_dir = $this->fileSystem->realpath($recipes_dir);
      assert(is_string($recipes_dir), 'Could not determine where Composer is configured to install recipes.');
      $search_in[] = $recipes_dir;
    }

    $finder = Finder::create()
      ->files()
      ->in($search_in)
      ->depth(1)
      // Without this, recipes that are symlinked into the project (e.g.,
      // path repositories) will be missed.
      ->followLinks()
      // The example recipe exists for documentation purposes only.
      ->notPath('example/')
      ->name('recipe.yml');

    $allowed = $this->configFactory->get('project_browser.admin_settings')
      ->get('allowed_projects.' . $this->getPluginId());
    if ($allowed) {
      $finder->path(
        array_map(fn (string $name) => $name . '/', $allowed),
      );
    }
    return $finder;
  }

  /**
   * Determines where Composer is configured to install recipes.
   *
   * @return string|null
   *   The absolute path where Composer is configured to install recipes, or
   *   NULL if it cannot be determined. The path may contain relative path
   *   references and symlinks will not have been resolved.
   */
  public static function getRecipesPath(): ?string {
    ['install_path' => $project_root] = InstalledVersions::getRootPackage();
    $file = $project_root . DIRECTORY_SEPARATOR . 'composer.json';

    if (file_exists($file)) {
      $data = file_get_contents($file);
      assert(is_string($data));
      $data = Json::decode($data);

      foreach ($data['extra']['installer-paths'] ?? [] as $path => $criteria) {
        if (in_array('type:' . Recipe::COMPOSER_PROJECT_TYPE, $criteria, TRUE)) {
          return $project_root . DIRECTORY_SEPARATOR . $path;
        }
      }
    }
    return NULL;
  }

}
