<?php

namespace Drupal\lms_user;

use Drupal\Core\Url;
use Drupal\entity\BulkFormEntityListBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a list controller for user_course entity.
 */
class BatchFormEntityListBuilder extends BulkFormEntityListBuilder {

  public function handleBatch($query, $redirect, $form_state, $analyze) {
    $class = static::class;
    // Generate the batch
    $batch = [
      'operations' => [],
      'finished' => $class . '::process_finish',
      'title' => $this->t('Processing batch'),
      'init_message' => $this->t('Downloading CSV is starting'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message' => $this->t('Downloading CSV is ocurring an error!'),
    ];
    $batch_id = time();

    // Add operations
    $ids = $analyze(new Request($query));
    $chunk = 9;
    $operations = [];
    $operations[] = [
      $class . '::process_set',
      [[1], [$analyze . '_headers', $query, $batch_id], $this->t('(Operation @operation)', ['@operation' => 'headers'])],
    ];
    if (!isset($ids['args'])) {
      $sets = array_chunk($ids, $chunk);
      foreach ($sets as $i => $set) {
        $operations[] = [
          $class . '::process_set',
          [$set, [$analyze . '_process', $query, $batch_id], $this->t('(Operation @operation)', ['@operation' => $i])],
        ];
      }
    }
    else {
      // This is downloading via query SQL
      [$idsquery] = $ids;
      $args = $ids['args'];
      $total = $idsquery->count()->execute();
      $operations[] = [
        $class . '::process_dynamic',
        [$analyze, $query, $total, 0, $chunk, [$analyze . '_process', $query, $batch_id], $this->t('(Operation @operation)', ['@operation' => 0])],
      ];
    }

    $operations[] = [
      $class . '::process_set',
      [[1], [$analyze . '_footers', $query, $batch_id], $this->t('(Operation @operation)', ['@operation' => 'footers'])],
    ];

    $batch['operations'] = $operations;

    // Execute the batch
    batch_set($batch);

    // Set redirect, a bit hacky here
    $b = &batch_get();
    $b['redirect'] = [$redirect, ['batch_id' => $batch_id]];

    return $batch;
  }

  /**
   * Batch 'finished' callback used by both batch 1 and batch 2.
   */
  public static function process_finish($success, $results, $operations) {
    $b = &batch_get();
    $messenger = \Drupal::messenger();

    if ($success) {
      // $messenger->addMessage(t('@count results processed.', ['@count' => count($results)]));
      // $messenger->addMessage(t('The final result was "%final"', ['%final' => end($results)]));
      $url = Url::fromRoute($b['redirect'][0], $b['redirect'][1]);

      $messenger->addMessage(
        t('A download is generated and is available <a href="@here">here</a>', [
          '@here' => $url->toString(),
        ])
      );
      // return new RedirectResponse($url->toString());
    }
    else {
      $error_operation = reset($operations);
      $messenger->addMessage(
        t('An error occurred while processing @operation with arguments : @args', [
          '@operation' => $error_operation[0],
          '@args' => print_r($error_operation[0], TRUE),
        ])
          );
    }
  }

  public static function process_dynamic($analyze, $query, $total, $offset, $chunk, $analyzeobj, $operation_details, &$context) {
    module_load_include('inc', 'lms_user', 'lms_user.performance');
    $start = get_timers();
    $class = static::class;
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_id'] = $offset;
      $context['sandbox']['max'] = ceil($total / $chunk);
    }

    // Now run the real query
    $context2 = [];
    $offset = $context['sandbox']['current_id'];
    $class::process_query($analyze, $query, $offset * $chunk, $chunk, $analyzeobj, $operation_details, $context2);
    $context['sandbox']['progress']++;
    $context['sandbox']['current_id']++;
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
    $end = get_timers();
    $context['message'] = t('Running batch "@id" / "@total". ETA for left time: @left mins', [
      '@id' => $offset * $chunk,
      '@total' => $context['sandbox']['max'] * $chunk,
      '@left' => number_format((($context['sandbox']['max'] - $offset) * ($end[1] - $start[1])) / 60),
    ]);
    // $message = display_timer_statistics($start, $end);
    // \Drupal::logger('lms_user')->error($message);
  }

  public static function process_query($analyze, $query, $offset, $limit, $analyzeobj, $operation_details, &$context) {
    $class = static::class;
    $set = $analyze(new Request($query), $offset, $limit)[0]->execute();
    return $class::process_set($set, $analyzeobj, $operation_details, $context);
  }

  public static function process_set($set, $analyzeobj, $operation_details, &$context) {
    [$analyze, $query, $batch_id] = $analyzeobj;

    if (empty($context['sandbox'])) {
      $context['sandbox'] = [];
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['data'] = [];
      $context['sandbox']['current_node'] = 0;
      $context['sandbox']['max'] = 10000;
    }

    $context['batch_id'] = $batch_id;
    foreach ($set as $id) {
      // Process
      $ids = $analyze($id, new Request($query), $context);

      // Store some results for post-processing in the 'finished' callback.
      // The contents of 'results' will be available as $results in the
      // 'finished' function (in this example, batch_example_finished()).
      $context['results'][] = $id . ' ' . $operation_details;

      // Update our progress information.
      ++$context['sandbox']['progress'];
      $context['sandbox']['current_node'] = $id;
      $context['message'] = t('Running Batch "@id" @details', [
        '@id' => $id,
        '@details' => $operation_details,
      ]);
    }

    // Inform the batch engine that we are not finished,
    // and provide an estimation of the completion level we reached.
  }

}
