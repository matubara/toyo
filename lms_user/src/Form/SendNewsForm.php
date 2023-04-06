<?php

namespace Drupal\lms_user\Form;

use Drupal\lms_mail\MailHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lms_user\Entity\UserCourse;
use Drupal\lms_user\UserManagerInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SendNewsForm extends FormBase {

  /**
   * @var array */
  protected $categories;

  /**
   * @var array */
  protected $emails;

  /**
   * @var \Drupal\lms_mail\MailHandlerInterface */
  protected $lms_mail_handle;

  /**
   * @var \Drupal\lms_user\UserManagerInterface */
  protected $lms_user_manager;

  /**
   * SendNewsForm constructor.
   *
   * @param \Drupal\lms_mail\MailHandlerInterface $lms_mail_handle
   */
  public function __construct(MailHandlerInterface $lms_mail_handle, UserManagerInterface $lms_user_manager) {
    $this->lms_mail_handle = $lms_mail_handle;
    $this->lms_user_manager = $lms_user_manager;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\lms_user\Form\SendNewsForm|static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lms_mail.handler'),
      $container->get('lms_user.manager')
    );
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return "send_news_form";
  }

  /**
   * @inheritDoc
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if ($trigger['#name'] == 'send-news') {
      $select = $form_state->getValue('user_course_select');
      $select = array_filter($select, function ($var) {
        return $var != 0;
      });
      if (empty($select)) {
        $form_state->setErrorByName('user_course_select', t('Please select who will receive the email.'));
      }
    }
    parent::validateForm($form, $form_state);
  }

  protected function init() {
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    /** @var \Drupal\taxonomy\Entity\Term[] $categories */
    $categories = $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')->loadByProperties(['vid' => 'category']);
    $categoriesOption = ['' => t('Any')];
    foreach ($categories as $key => $category) {
      $category = $category->hasTranslation($langcode) ? $category->getTranslation($langcode) : $category;
      $categoriesOption[$category->id()] = $category->getName();
    }
    $this->categories = $categoriesOption;

    /** @var \Drupal\node\NodeInterface[] $emails */
    $emails = $terms = \Drupal::entityTypeManager()
      ->getStorage('node')->loadByProperties(['type' => 'email_template']);
    $emailsOptions = [];
    foreach ($emails as $email) {
      $email = $email->hasTranslation($langcode) ? $email->getTranslation($langcode) : $email;
      $emailsOptions[$email->id()] = $email->getTitle();
    }
    $this->emails = $emailsOptions;
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->init();
    $request = \Drupal::request();
    $category = $request->query->get('category') ?? '';
    $course_start_date = $request->query->get('start');
    $course_end_date = $request->query->get('end');
    $participation = $request->query->get('participation');
    $current_page = $request->get('page') ?: 0;
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $form['wrapper'] = [
      '#type' => 'container',
      '#weight' => -100,
    ];
    $form['wrapper']['filter'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form--inline', 'clearfix']],
      '#weight' => -100,
    ];

    $form['wrapper']['filter']['category'] = [
      '#title' => t('Category'),
      '#type' => 'select',
      '#options' => $this->categories,
      '#default_value' => $category,
    ];
    $form['wrapper']['filter']['course_start_date'] = [
      '#type' => 'date',
      '#title' => t('Course from date'),
      '#default_value' => $course_start_date,
    ];
    $form['wrapper']['filter']['course_end_date'] = [
      '#type' => 'date',
      '#title' => t('Course to date'),
      '#default_value' => $course_end_date,
    ];
    $form['wrapper']['filter']['participation'] = [
      '#title' => t('Participation'),
      '#type' => 'select',
      '#options' => ['' => t('Any'), 1 => t('True'), 0 => t('False')],
      '#default_value' => $participation,
    ];

    $form['wrapper']['filter']['actions'] = [
      '#type' => 'actions',
    ];
    $form['wrapper']['filter']['actions']['submit_filter'] = [
      '#type' => 'submit',
      '#value' => t('Filter'),
    ];

    $form['wrapper']['send']['email'] = [
      '#type' => 'select',
      '#title' => t('Select email template'),
      '#options' => $this->emails,
    ];
    $form['wrapper']['send']['actions'] = [
      '#type' => 'actions',
    ];
    $form['wrapper']['send']['actions']['send_news'] = [
      '#type' => 'submit',
      '#value' => t('Send New'),
      '#submit' => [[$this, 'sendNews']],
      '#name' => 'send-news',
    ];
    if (empty($this->emails)) {
      $form['wrapper']['send']['actions']['send_news']['#attributes']['disabled'] = 'disabled';
    }

    if ($category) {
      $class_id = \Drupal::entityQuery('node')
        ->condition('type', 'class')
        ->condition('field_category', $category)
        ->execute();
    }
    else {
      $class_id = \Drupal::entityQuery('node')
        ->condition('type', 'class')
        ->execute();
    }
    $user_course_ids = [];
    $ids = [];
    if (count($class_id)) {
      $query_course = \Drupal::entityQuery('commerce_product')
        ->condition('type', 'course')
        ->condition('field_class', $class_id, "IN");
      if ($course_start_date and $course_end_date) {
        $start = date(DATE_ATOM, strtotime($course_start_date));
        $end = date(DATE_ATOM, strtotime($course_end_date . " 23:59:59"));
        $group = $query_course->andConditionGroup()
          ->condition('field_day_of_the_event.value', $start, '>=')
          ->condition('field_day_of_the_event.value', $end, '<=');
        $query_course->condition($group);
      }
      else {
        if ($course_start_date) {
          $start = date(DATE_ATOM, strtotime($course_start_date));
          $query_course->condition('field_day_of_the_event.value', $start, '>=');
        }
        if ($course_end_date) {
          $end = date(DATE_ATOM, strtotime('+24 hours', $course_end_date));
          $query_course->condition('field_day_of_the_event.end_value', $end, '<=');
        }
      }
      $course_ids = $query_course->execute();
      if ($course_ids) {
        $query = \Drupal::entityQuery('user_course');
        $query->condition('course', $course_ids, 'IN');
        if ($participation != NULL) {
          if ($participation == 1) {
            $query->exists('post_survey');
          }
          elseif ($participation == 0) {
            $query->notExists('post_survey');
          }
        }
        $query->condition('uid', 1, '<>');
        $query_count = $query;
        $ids = $query->execute();
        // $query->range(20 * $current_page, 20);
        $user_course_ids = $query->execute();
      }

    }

    /** @var \Drupal\lms_user\Entity\UserCourseInterface[] $user_courses */
    $user_courses = UserCourse::loadMultiple($user_course_ids);
    $data = [];
    foreach ($user_courses as $user_course) {
      $user = $user_course->getUser();
      /** @var \Drupal\commerce_product\Entity\ProductInterface $course */
      $course = $this->lms_user_manager->getCourseFromUserCourse($user_course);
      /** @var \Drupal\lms_user\Entity\UserClassInterface $class */
      $user_class = $user_course->class->entity;
      $course = $course->hasTranslation($langcode) ? $course->getTranslation($langcode) : $course;
      /** @var \Drupal\node\NodeInterface $class */
      $class = $user_class->class->entity;
      $class = $class->hasTranslation($langcode) ? $class->getTranslation($langcode) : $class;
      $date = $this->lms_user_manager->getCourseEndDateRangeFormat($course);
      $user = $user_course->getUser();
      $row = [];
      if ($user) {
        $row['class']['data']['#markup'] = "<a href='" . $class->toUrl()
          ->toString() . "' >" . $class->getTitle() . "</a>";
        $row['course']['data']['#markup'] = "<a href='" . $course->toUrl()
          ->toString() . "' >" . $course->getTitle() . "</a>";
        $row['fullname']['data']['#markup'] = "<a href='" . $user->toUrl()
          ->toString() . "' >" . $user->get('field_full_name')
          ->getString() . "</a>";
        $row['email']['data']['#markup'] = "<a href='" . $user->toUrl()
          ->toString() . "' >" . $user->getEmail() . "</a>";
        $row['date']['data']['#markup'] = $date;
        $row['participation']['data'] = $user_course->get('post_survey')
          ->getString() ? t('True') : t('False');
        $data[$user_course->id()] = $row;
      }
    }
    $form['send_news']['user_course_select'] = [
      '#type' => 'tableselect',
      '#header' => [
        'class' => $this->t('Class name'),
        'course' => $this->t('Course name'),
        'fullname' => $this->t('User name', [], ['context' => 'user_course']),
        'email' => $this->t('User email address'),
        'date' => $this->t('Course period'),
        'participation' => $this->t('Participation'),
      ],
      '#options' => $data,
    ];
    return $form;
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $query = [];
    if ($values['category']) {
      $query['category'] = $values['category'];
    }

    if ($values['course_start_date']) {
      $query['start'] = $values['course_start_date'];
    }
    if ($values['course_end_date']) {
      $query['end'] = $values['course_end_date'];
    }
    if ($values['participation'] !== '') {
      $query['participation'] = $values['participation'];
    }

    $form_state->setRedirect('lms_user.send_news', $query);

  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function sendNews(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $user_course_select = $values['user_course_select'];

    $user_courses = UserCourse::loadMultiple($user_course_select);
    $email_id = $values['email'];
    $email = Node::load($email_id);
    foreach ($user_courses as $user_course) {
      $user = $user_course->getUser();
      if (!$user->isAnonymous()) {
        $user_langcode = $user->get('preferred_langcode')->getString();
        $email_template = $email->hasTranslation($user_langcode) ? $email->getTranslation($user_langcode) : $email;
        $body = $email_template->get('body')->first()->getValue()['value'];
        $subject = $email_template->getTitle();
        $body = [
          '#theme' => 'lms_send_news',
          '#body' => $body,
        ];
        $result = $this->lms_mail_handle->sendMail($user->getEmail(), $subject, $body, ['id' => 'send_news']);
      }
    }
    \Drupal::messenger()->addMessage(t('Send news success.'));
  }

}
