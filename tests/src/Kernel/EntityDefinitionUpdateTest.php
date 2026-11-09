<?php

namespace Drupal\Tests\entity_overview\Kernel;

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests registration of missing overview definitions without changing config.
 *
 * @group entity_overview
 */
#[Group('entity_overview')]
#[RunTestsInSeparateProcesses]
class EntityDefinitionUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'text',
    'filter',
    'datetime',
    'taxonomy',
    'entity_overview',
  ];

  /**
   * The module does not yet provide configuration schema for overviews.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Tests both missing and already registered definitions.
   *
   * @dataProvider installedDefinitionProvider
   */
  #[DataProvider('installedDefinitionProvider')]
  public function testDefinitionRepair(bool $already_installed): void {
    $this->container->get('module_handler')->loadInclude('entity_overview', 'install');
    $this->container->get('keyvalue')->get('system.schema')->set('entity_overview', 9006);
    $repository = $this->container->get('entity.last_installed_schema.repository');
    $entity_type_manager = $this->container->get('entity_type.manager');
    if ($already_installed) {
      $repository->setLastInstalledDefinition($entity_type_manager->getDefinition('entity_overview'));
    }
    $definition_before = $repository->getLastInstalledDefinition('entity_overview');

    $config_name = 'entity_overview.overview.existing';
    $this->container->get('config.factory')->getEditable($config_name)->setData([
      'id' => 'existing',
      'uuid' => 'a9ff1a3e-7f72-47f3-b150-21968395987a',
      'label' => 'Existing overview',
      'entity_bundles' => [],
      'fields' => [],
      'engine_id' => 'entity_query',
      'engine_settings' => ['custom' => 'preserve this setting'],
      'sort_field' => 'created',
      'show_total' => '1',
    ])->save();
    $config_storage = $this->container->get('config.storage');
    $before = $config_storage->read($config_name);
    $update_manager = $this->container->get('entity.definition_update_manager');
    if (!$already_installed) {
      $this->assertSame(EntityDefinitionUpdateManagerInterface::DEFINITION_CREATED, $update_manager->getChangeList()['entity_overview']['entity_type']);
    }

    // A repeated hook call must also preserve the now-installed definition.
    for ($run = 0; $run < 2; $run++) {
      entity_overview_update_9007();
      $this->assertNotNull($repository->getLastInstalledDefinition('entity_overview'));
      if ($already_installed) {
        $this->assertEquals($definition_before, $repository->getLastInstalledDefinition('entity_overview'));
      }
      $this->assertArrayNotHasKey('entity_overview', $update_manager->getChangeList());
      $this->assertSame($before, $config_storage->read($config_name));
      $storage = $entity_type_manager->getStorage('entity_overview');
      $storage->resetCache();
      $overview = $storage->load('existing');
      $this->assertNotNull($overview);
      $this->assertSame($before['uuid'], $overview->uuid());
      $this->assertSame($before['engine_settings'], $overview->get('engine_settings'));
    }
  }

  /**
   * Provides the supported installed-definition states.
   */
  public static function installedDefinitionProvider(): array {
    return ['missing' => [FALSE], 'already installed' => [TRUE]];
  }

}
