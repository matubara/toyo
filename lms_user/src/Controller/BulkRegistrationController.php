<?php

namespace Drupal\lms_user\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Element\Date;

class BulkRegistrationController extends ControllerBase {

  /**
   * Show export result file list.
   */
  public function listExportCSV() {
    $node_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', 'csv_import')
      ->sort('created', 'DESC')
      ->pager(20)
      ->execute();
    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($node_ids);
    $build['data'] = [
      '#theme' => 'table',
      '#header' => [
        'Import file',
        'Export file',
        'Created',
      ],
    ];
    foreach ($nodes as $node) {
      $import_file = $node->get('field_csv_import_file')->first()->entity;
      $export_file = $node->get('field_csv_export_file')->first()->entity;
      $build['data']['#rows'][] = [
        'import_file' => [
          'data' => new FormattableMarkup('<a href = ":link">@name</a>', [
            ':link' => $import_file ? $import_file->createFileUrl() : '',
            '@name' => $import_file ? $import_file->label() : '',
          ]),
        ],
        'export_file' => [
          'data' => new FormattableMarkup('<a href = ":link">@name</a>', [
            ':link' => $export_file ? $export_file->createFileUrl() : '',
            '@name' => $export_file ? $export_file->label() : '',
          ]),
        ],
        'created' => date('Y-m-d h:i:s', $node->get('created')->value),
      ];
    }

    $build['pager'] = [
      '#type' => 'pager',
    ];
    return $build;
  }

}
