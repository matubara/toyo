<?php

namespace Drupal\lms_user\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\Order;
use Drupal\lms_order\OrderManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use Drupal\commerce_product\Entity\ProductVariation;

/**
 * Provides a form for group course application for students.
 */
class UserBulkCourseApplicationForm extends FormBase {

  /**
   * The storage handler class for entites.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityStorage;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Custom LMS order manager service.
   *
   * @var \Drupal\lms_order\OrderManager
   */
  protected $orderManager;

  /**
   * The datetime.time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $timeService;

  /**
   * Setup logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity
   *   The Entity type manager service.
   * @param \Drupal\Core\Entity\MessengerInterface $messenger_service
   *   The messenger service.
   * @param \Drupal\lms_order\OrderManager $order_manager
   *   The Order manager service.
   * @param \Drupal\Component\Datetime\TimeInterface $time_service
   *   The datetime.time service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   */
  public function __construct(EntityTypeManagerInterface $entity,
  MessengerInterface $messenger_service,
  OrderManager $order_manager,
  TimeInterface $time_service,
  LoggerChannelFactoryInterface $logger_factory) {
    $this->entityStorage = $entity;
    $this->messenger = $messenger_service;
    $this->orderManager = $order_manager;
    $this->timeService = $time_service;
    $this->logger = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('lms_order.manager'),
      $container->get('datetime.time'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'student_bulk_course_application_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['bulk_course_widget'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Group Course Submission'),
    ];

    $form['bulk_course_widget']['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload CSV'),
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
    ];

    $form['bulk_course_widget']['actions'] = [
      '#type' => 'actions',
    ];
    $form['bulk_course_widget']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $csv_file = $this->parseImportFile(reset($form_state->getValue('file')));
    $enrollees = $csv_file['file_data'];

    if (empty($enrollees[0][0])) {
      // Empty data found.
      $message = $this->t('No data found in csv (@file_name)', [
        '@file_name' => $csv_file['file_name'],
      ]);
      $this->messenger->addMessage($message, 'error');
      $this->logger('csv_import')->error($message);
    }
    else {
      $header_keys = [];
      $required_headers = ['メールアドレス', '講座名', 'コース名', '支払いステータス'];
      $row = 0;

      // Check if specific headers are present and in correct order.
      foreach ($enrollees[0] as $enrolee) {
        if (strpos($enrolee, $required_headers[$row]) !== FALSE) {
          $header_keys[] = $required_headers[$row];
        }

        $row++;
      }

      if (!empty(array_diff($required_headers, $header_keys))) {
        // Invalid csv headers.
        $message = $this->t('Missing or invalid csv header (@file_name)', [
          '@file_name' => $csv_file['file_name'],
        ]);
        $this->messenger->addMessage($message, 'error');
        $this->logger('csv_import')->error($message);
      }
      else {
        // Check and collect errors.
        array_shift($enrollees);
        $enrollees = $this->assignHeaderKeys($header_keys, $enrollees);
        $csv_row = 2;
        $error_logs = [];

        foreach ($enrollees as $enrolee) {
          $email = $enrolee[$required_headers[0]];
          $class_name = $enrolee[$required_headers[1]];
          $course_name = $enrolee[$required_headers[2]];
          $student = $this->getUserByEmail($email);
          $class = $this->getClassByTitle($class_name);
          $course = $this->getCourseByTitle($course_name, $class);
          $payment_status = $enrolee[$required_headers[3]];
          // CSV array for report entry.
          $csv_data = [
            'email' => $email,
            'class_name' => $class_name,
            'course_name' => $course_name,
            'payment_status' => $payment_status,
            'row' => $csv_row,
            'file_name' => $csv_file['file_name'],
          ];

          // Check for existing user course application.
          if (!empty($student) && !empty($class) && !empty($course)) {
            $student_application_course = $this->getUserAppliedCourse($course, $student);
            // Proceed with import if new application.
            if (!$student_application_course) {
              $free_course = $this->freeCourse($course);
              $status = $this->getStatus($payment_status);

              // Free courses, we bypass empty payment status
              if ($free_course) {
                $status = 1;
              }

              if (!$status) {
                // Log invalid payment status
                $payment_status_display = (!empty($payment_status)) ? $payment_status : 'N/A';
                $error_message = $this->t('Payment status invalid');
                $error_logs[] = $csv_row . ', ' . $email . ', ' . $class_name . ', ' . $course_name . ', ' . $payment_status . ', ' . $error_message;

                $this->messenger->addMessage($this->t('Invalid payment status: @status', ['@status' => $payment_status_display]), 'error');
                // Add entry to CSV report.
                $csv_data['import_status'] = 'fail';
                $csv_data['import_status_detail'] = $error_message;
                $this->logCsvReportEntry($csv_data);
              }
              else {
                $csv_data['import_status'] = 'ok';

                // For paid courses, only accept valid payment status
                $order_status = ($status === 1) ? 'completed' : 'draft';

                // Log successful import.
                $log_data = $csv_row . ', ' . $email . ', ' . $class_name . ', ' . $course_name . ', ' . $payment_status . ' OK (' . $csv_file['file_name'] . ')';
                $message = $this->t('Imported successfully (@file_name)', [
                  '@file_name' => $csv_file['file_name'],
                ]);

                $this->logger('csv_import')->notice($message . '<br>' . $log_data);
                // Add entry to CSV report.
                $this->logCsvReportEntry($csv_data);
                $this->processCourseOrder($student, $course, $order_status);
              }
            }
            else {
              // Log error for existing user.
              $this->messenger->addMessage($this->t('Account with email @email has already applied to @course under @class (@file_name)',
              [
                '@email' => $email,
                '@course' => $course_name,
                '@class' => $class_name,
                '@file_name' => $csv_file['file_name'],
              ]), 'error');

              $error_message = $this->t('The user already applied');
              $error_logs[] = $csv_row . ', ' . $email . ', ' . $class_name . ', ' . $course_name . ', ' . $payment_status . ', ' . $error_message;

              // Add entry to CSV report.
              $csv_data['import_status'] = 'fail';
              $csv_data['import_status_detail'] = $error_message;
              $this->logCsvReportEntry($csv_data);
            }
          }
          else {
            // Log errors.
            $log_message = [];

            if (empty($student)) {
              $log_message[] = $this->t('User not found');
              $this->messenger->addMessage($this->t('Could not find user with email: @email', ['@email' => $email]), 'error');
            }

            if (empty($class)) {
              $log_message[] = $this->t('Class not found');
              $this->messenger->addMessage($this->t('Could not find class: @title', ['@title' => $class_name]), 'error');
            }

            if (empty($course)) {
              $log_message[] = $this->t('Course not found');
              $this->messenger->addMessage($this->t('Could not find course: @title', ['@title' => $course_name]), 'error');
            }

            $error_logs[] = $csv_row . ', ' . $email . ', ' . $class_name . ', ' . $course_name . ', ' . $payment_status . ', ' . implode(', ', $log_message);

            // Add entry to CSV report.
            $csv_data['import_status'] = 'fail';
            $csv_data['import_status_detail'] = implode(', ', $log_message);

            $this->logCsvReportEntry($csv_data);
          }

          $csv_row++;
        }

        // Log error meesage as a single log entry.
        if (!empty($error_logs)) {
          $this->logCsvErrors($error_logs, $csv_file['file_name']);
          $this->messenger->addMessage($this->t('Error found during import. Please check <a href="@page_link">CSV Import Report</a> page for more detail.',
            ['@page_link' => Url::fromRoute('entity.csv_import_user_course_log.collection')->toString()]), 'error');
        }
        else {
          // Display compelte message.
          $this->messenger->addMessage($this->t('Import complete!'));
        }

      }
    }
  }

  /**
   * Load status via string
   * FALSE meant wrong
   * 1 meant ok
   * 2 mean not ok
   */
  public function getStatus($string) {
    $valid_payment_status = ['支払済', '未払い'];
    $string = trim($string);
    return $string === $valid_payment_status[0] ? 1 : ($string === $valid_payment_status[1] ? 2 : FALSE);
  }

  /**
   * Retrieve data from file.
   */
  public function parseImportFile($file_id) {
    $csv_file = $this->entityStorage->getStorage('file')->load($file_id);
    $enrollees = [];

    if (($csv = fopen($csv_file->uri->getString(), 'r')) !== FALSE) {
      while (($row_data = fgetcsv($csv, 0, ',')) !== FALSE) {
        $enrollees[] = $row_data;
      }

      $file_name = basename($csv_file->uri->getString());
      fclose($csv);

      $enrollees_data = [
        'file_name' => $file_name,
        'file_data' => $enrollees,
      ];

      return $enrollees_data;
    }
  }

  /**
   * Assign header data as array keys.
   */
  public function assignHeaderKeys($keys, $items) {
    $formatted_items = [];

    foreach ($items as $item) {
      $formatted_items[] = array_combine($keys, $item);
    }

    return $formatted_items;
  }

  /**
   * Get user information by email.
   */
  public function getUserByEmail($mail) {
    $users = $this->entityStorage->getStorage('user')
      ->loadByProperties(['mail' => $mail]);
    $user = reset($users);

    if ($user) {
      return $user;
    }
  }

  /**
   * Get class information by title.
   */
  public function getClassByTitle($title) {
    $query = $this->entityStorage->getStorage('node')
      ->getQuery()
      ->condition('title', $title, '');
    $ids = $query->execute();
    $id = reset($ids);
    $class = $this->entityStorage->getStorage('node')->load($id);

    if ($class) {
      return $class;
    }
  }

  /**
   * Get course information by title.
   */
  public function getCourseByTitle($title, $class) {
    if (!empty($class)) {
      // Apart from course name, we include class to find the valid course.
      $query = $this->entityStorage->getStorage('commerce_product')
        ->getQuery()
        ->condition('field_class', $class->id())
        ->condition('title', $title, '');
      $ids = $query->execute();
      $id = reset($ids);
      $course = $this->entityStorage->getStorage('commerce_product')->load($id);

      if ($course) {
        return $course;
      }
    }
  }

  /**
   * Get course information by title.
   */
  public function freeCourse($course) {
    $variation_id = $course->getVariationIds()[0];
    $product_variation = $variation_id ? ProductVariation::load($variation_id) : FALSE;
    $price = $product_variation && $product_variation->getPrice() ? $product_variation->getPrice()->getNumber()[0] : FALSE;
    return !($price && floatval($price) > 0);
  }

  /**
   * Process course order.
   *
   * @param object $student
   *   The account user entity.
   * @param object $course
   *   The user course entity.
   * @param string $order_status
   *   The order status.
   */
  public function processCourseOrder($student, $course, $order_status) {
    $variation_id = $course->getVariationIds()[0];
    $product_variation = $this->entityStorage->getStorage('commerce_product_variation')->load($variation_id);

    $order_item = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => $course->getVariationIds()[0],
      'unit_price' => $product_variation->getPrice(),
      'title' => $product_variation->getTitle(),
      'quantity' => 1,
    ]);
    $order_item->setData('is_manual', TRUE);
    $order_item->save();

    $order = Order::create([
      'type' => 'default',
      'mail' => $student->getEmail(),
      'uid' => $student->id(),
      'store_id' => 1,
      'order_items' => [$order_item],
      'state' => $order_status,
      'placed' => $this->timeService->getCurrentTime(),
    ]);
    $order->save();
    $order_id = $order->id();
    $order->set('order_number', $order_id);
    $order->save();

    // Create user course entry.
    $this->orderManager->finalizeOrder($order);
    // Update course entry status and csv flag.
    $user_course_entry = $this->entityStorage->getStorage('user_course')
      ->loadByProperties(['order' => $order_id]);
    $user_course = reset($user_course_entry);
    if ($user_course) {
      // Set flag for CSV application.
      $user_course->set('field_application_by_csv', 1);
      // Unpublish user course if order is not yet completed.
      if ($order_status != 'completed') {
        $user_course->set('status', 0);
      }
      $user_course->save();
    }
  }

  /**
   * Get existing application of student.
   *
   * @param object $course
   *   The user course entity.
   * @param object $user
   *   The account user entity.
   */
  public function getUserAppliedCourse($course, $user) {
    $courses = $this->entityStorage->getStorage('user_course')
      ->loadByProperties([
        'course' => $course->id(),
        'uid' => $user->id(),
      ]);

    return $courses ? reset($courses) : NULL;
  }

  /**
   * Create single entry error log.
   *
   * @param array $logs
   *   The log information.
   * @param string $file_name
   *   The CSV file name of import file.
   */
  public function logCsvErrors(array $logs, $file_name) {
    $message = $this->t('@total Error(s) found (@file_name)', [
      '@total' => count($logs),
      '@file_name' => $file_name,
    ]);

    $this->logger('csv_import')->error($message . '<br>' . implode('<br>', $logs));
  }

  /**
   * Add to CSV report entry.
   *
   * @param array $csv_data
   *   The CSV information.
   */
  public function logCsvReportEntry(array $csv_data) {
    // Format payment status value.
    $payment_status = NULL;
    if (!empty($csv_data['payment_status'])) {
      if ($csv_data['payment_status'] == '支払済') {
        $payment_status = 'paid';
      }
      elseif ($csv_data['payment_status'] == '未払い') {
        $payment_status = 'unpaid';
      }
      else {
        $payment_status = $csv_data['payment_status'];
      }
    }

    $import_status = (!empty($csv_data['import_status_detail'])) ? $csv_data['import_status_detail'] : NULL;

    // Add CSV import log entry.
    $csv_report_log = $this->entityStorage->getStorage('csv_import_user_course_log')
      ->create([
        'line_number' => $csv_data['row'],
        'name' => $this->t('CSV import: @import_info', [
          '@import_info' => $csv_data['email'] . ' - ' . $csv_data['course_name'],
        ]),
        'import_status' => $csv_data['import_status'],
        'import_status_detail' => $import_status,
        'email' => $csv_data['email'],
        'class' => $csv_data['class_name'],
        'course' => $csv_data['course_name'],
        'payment_status' => $payment_status,
        'file_name' => $csv_data['file_name'],
      ]);
    $csv_report_log->save();
  }

}
