<?php

namespace Drupal\lms_user;

use Drupal\node\Entity\Node;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\OrderItemMatcherInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lms_commerce\CourseManagerInterface;
use Drupal\lms_commerce\CourseStatusInterface;
use Drupal\lms_user\Entity\UserClass;
use Drupal\lms_user\Entity\UserClassInterface;
use Drupal\lms_user\Entity\UserCourse;
use Drupal\lms_user\Entity\UserCourseInterface;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\webform\WebformSubmissionInterface;

class UserManager implements UserManagerInterface {
  use StringTranslationTrait;

  /**
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * @var \Drupal\lms_commerce\CourseManagerInterface
   */
  protected $courseManager;

  /**
   * @return \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The order item matcher.
   *
   * @var \Drupal\commerce_cart\OrderItemMatcherInterface
   */
  protected $orderItemMatcher;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userClassStorage;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userCourseStorage;

  /**
   * UserManager constructor.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    CourseManagerInterface $course_manager,
    AccountProxyInterface $current_user,
    CartProviderInterface $cartProvider,
    CartManagerInterface $cartManager,
    OrderItemMatcherInterface $order_item_matcher,
    LanguageManagerInterface $language_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->userClassStorage = $entity_type_manager->getStorage('user_class');
    $this->userCourseStorage = $entity_type_manager->getStorage('user_course');
    $this->courseManager = $course_manager;
    $this->currentUser = $current_user;
    $this->cartProvider = $cartProvider;
    $this->cartManager = $cartManager;
    $this->orderItemMatcher = $order_item_matcher;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function addClassPreSurvey(WebformSubmissionInterface $submission) {
    $userClass = \Drupal::routeMatch()->getParameter('user_class');

    if ($userClass instanceof UserClass) {
      $userClass->set('pre_survey', $submission->id());
      $userClass->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addCoursePostSurvey(WebformSubmissionInterface $submission) {
    $course = \Drupal::routeMatch()->getParameter('course');
    $user_id = \Drupal::currentUser()->id();
    $userCourse = $this->getUserCoursePostSurvey($course, $user_id);

    if ($userCourse instanceof UserCourse) {
      $userCourse->set('post_survey', $submission->id());
      $userCourse->save();
      \Drupal::moduleHandler()->invokeAll('course_survey_added', [$userCourse, $submission]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addCourseToCart(array $course_ids) {
    $currentUser = User::load(\Drupal::currentUser()->id());
    $order_type = 'default';
    $cart = $this->cartProvider->getCart($order_type);

    if (!$cart) {
      $cart = $this->cartProvider->createCart($order_type);
    }

    foreach ($course_ids as $course_id) {
      $course = Product::load($course_id);

      if ($course) {
        if ($this->courseManager->getCourseStatus($course) !== CourseStatusInterface::RECRUITING) {
          continue;
        }
        /** @var \Drupal\commerce_product\Entity\ProductInterface $course */
        if (!$this->hasUserCourse($course, $currentUser)) {
          if ($variation = $course->getDefaultVariation()) {
            $newOrderItem = $this->cartManager->createOrderItem($variation);
            $matchingItem = $this->orderItemMatcher->match($newOrderItem, $cart->getItems());

            if (!$matchingItem) {
              $this->cartManager->addOrderItem($cart, $newOrderItem);
            }
          }
        }
      }
    }
    \Drupal::messenger()->deleteAll();

    if ($cart->hasItems()) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function clearLMSData(string $type = 'all') {
    if ($type === 'all' || $type === 'user') {
      $user_ids = \Drupal::entityQuery('user')
        ->condition('roles', ['administrator', 'lms_admin', 'lms_staff'], 'NOT IN')
        ->execute();

      if ($user_ids) {
        $user_storage_handler = $this->entityTypeManager->getStorage('user');
        $users = $user_storage_handler->loadMultiple($user_ids);
        $user_storage_handler->delete($users);
      }
    }

    if ($type === 'all' || $type === 'user_course') {
      \Drupal::database()
        ->query('DELETE FROM user_course')
        ->execute();
      \Drupal::database()
        ->query('DELETE FROM user_course_field_data')
        ->execute();
    }

    if ($type === 'all' || $type === 'user_class') {
      \Drupal::database()
        ->query('DELETE FROM user_class')
        ->execute();
      \Drupal::database()
        ->query('DELETE FROM user_class_field_data')
        ->execute();
    }

    if ($type === 'all' || $type === 'commerce_order') {
      $commerce_order_storage_handler = $this->entityTypeManager->getStorage('commerce_order');
      $orders = $commerce_order_storage_handler->loadMultiple();
      $commerce_order_storage_handler->delete($orders);
    }

    if ($type === 'all' || $type === 'lms_certificate') {
      \Drupal::database()
        ->query('DELETE FROM lms_certificate')
        ->execute();
      \Drupal::database()
        ->query('DELETE FROM lms_certificate_field_data')
        ->execute();
    }

    \Drupal::logger('system')->info('Clear data ' . $type . ' completed');
  }

  /**
   * {@inheritdoc}
   */
  public function createUserClass(NodeInterface $class, UserInterface $user) {
    $userClass = $this->userClassStorage->create([
      'class' => $class->id(),
      'name' => $class->label(),
      'uid' => $user->id(),
    ]);
    $userClass->save();

    return $userClass;
  }

  /**
   * {@inheritdoc}
   */
  public function createUserCourse(UserClassInterface $userClass, ProductInterface $course, UserInterface $user, OrderInterface $order) {
    // Update if user course entry already exist.
    $existing_course = $this->userCourseStorage->loadByProperties([
      'course' => $course->id(),
      'class' => $userClass->id(),
      'order' => $order->id(),
      'uid' => $user->id(),
    ]);

    if (!empty($existing_course)) {
      $existing_course = reset($existing_course);
      $existing_course->set('status', 1)->save();
      $userCourse = $existing_course;
    }
    else {
      // Create new user course entry if does not exist.
      $userCourse = $this->userCourseStorage->create([
        'name' => $course->label(),
        'course' => $course->id(),
        'class' => $userClass->id(),
        'order' => $order->id(),
        'uid' => $user->id(),
        'status' => 1,
      ]);
      $userCourse->save();
    }

    return $userCourse;
  }

  /**
   * {@inheritdoc}
   */
  public function getClassEndDateRangeFormat(NodeInterface $class) {
    return $this->dateRange($class, 'field_period');
  }

  /**
   * {@inheritdoc}
   */
  public function dateRange($class, $field) {
    $date_formatter = \Drupal::service('date.formatter');
    $class_start_date = $this->time($class->get($field)->value);
    $class_end_date = $this->time($class->get($field)->end_value);

    return t('@start ~ @end', [
      '@start' => $date_formatter->format($class_start_date, 'custom', t('F d, Y (l)')),
      '@end' => $date_formatter->format($class_end_date, 'custom', t('F d, Y (l)')),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getClassFromUserClass(UserClassInterface $userClass) {
    $classes = $userClass->get('class')->referencedEntities();

    return $classes ? reset($classes) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getClassPreSurvey(UserClassInterface $userClass) {
    $class = $this->getClassFromUserClass($userClass);

    if ($class) {
      $preSurveys = $class->get('field_pre_survey')->referencedEntities();

      return $preSurveys ? reset($preSurveys) : NULL;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCourseEndDateRangeFormat(ProductInterface $course) {
    return $this->dateRange($course, 'field_day_of_the_event');
  }

  /**
   * {@inheritdoc}
   */
  public function getCourseFromUserCourse(UserCourseInterface $userCourse) {
    $courses = $userCourse->get('course')->referencedEntities();

    return $courses ? reset($courses) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCoursePostSurvey(UserCourseInterface $userCourse) {
    $course = $this->getCourseFromUserCourse($userCourse);

    if ($course) {
      $postSurveys = $course->get('field_post_survey')->referencedEntities();

      return $postSurveys ? reset($postSurveys) : NULL;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDateData(string $date) {
    $data['year'] = date('Y', strtotime($date));
    $data['month'] = date('m', strtotime($date));
    $data['month_string'] = date('F', strtotime($date));
    $data['day'] = date('d', strtotime($date));
    $data['day_of_week'] = date('l', strtotime($date));
    $data['day_of_week_short'] = date('D', strtotime($date));
    $data['hour'] = date('h', strtotime($date));
    $data['minutes'] = date('i', strtotime($date));
    $data['A'] = date('A', strtotime($date));

    return $data;
  }

  /**
   * {@inheritdoc}
   * @TODO: When having more time convert this as approach in dateRange
   */
  public function getDateFormatResponse(string $langcode, array $date, ?string $type = NULL) {
    if ($type) {
      if ($langcode === 'ja') {
        $date_string = $date['day_of_week_short'];
      }
      else {
        $date_string = $date['day_of_week'];
      }

      if ($type === 'date_of_event') {
        return t('@year.@month.@day', [
          '@year' => $date['year'],
          '@month' => $date['month'],
          '@day' => $date['day'],
        ]) .
          ' (' .
          t($date_string) .
          ') ' .
          t('@hour:@minutes @A', [
            '@hour' => $date['hour'],
            '@minutes' => $date['minutes'],
            '@A' => $date['A'],
          ]);
      }

      return NULL;
    }

    if ($langcode === 'en') {
      return t('@month @day, @year (@day_of_week)', [
        '@month' => $date['month_string'],
        '@day' => $date['day'],
        '@year' => $date['year'],
        '@day_of_week' => $date['day_of_week'],
      ]);
    }

    if ($langcode === 'ja') {
      return t('@year year @month month @day day', [
        '@year' => $date['year'],
        '@month' => $date['month'],
        '@day' => $date['day'],
      ]) .
        ' (' .
        t($date['day_of_week_short']) .
        ')';
    }

    if ($langcode === 'zh-hans') {
      return t('@year year @month month @day day', [
        '@year' => $date['year'],
        '@month' => $date['month'],
        '@day' => $date['day'],
      ]) .
        ' (' .
        t($date['day_of_week']) .
        ')';
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmailUserClass(UserClassInterface $userClass) {
    $user = $userClass->get('uid')->referencedEntities();

    return $user ? reset($user)->getEmail() : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getEmailUserCourse(UserCourseInterface $userCourse) {
    $user = $userCourse->get('uid')->referencedEntities();

    return $user ? reset($user)->getEmail() : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstLanguages() {
    $langcode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();

    return [
      '' => t('- Select -'),
      'arabic' => t('Arabic'),
      'italian' => t('Italian'),
      'ukrainian' => t('Ukrainian'),
      'english' => $langcode === 'ja' ? '英語' : 'English',
      'korean' => t('Korean'),
      'javanese' => t('Javanese'),
      'spanish' => t('Spanish'),
      'thai' => t('Thai'),
      'tamil' => t('Tamil'),
      'chinese' => t('Chinese'),
      'telugu' => t('Telugu'),
      'german' => t('German'),
      'turkish' => t('Turkish'),
      'hindi' => t('Hindi'),
      'french' => t('French'),
      'vietnamese' => t('Vietnamese'),
      'bengali' => t('Bengali'),
      'portuguese' => t('Portuguese'),
      'malay' => t('Malay'),
      'russian' => t('Russian'),
      'japanese' => t('Japanese'),
      '0' => t('Other'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getGenderOptions() {
    return [
      'male' => t('Male'),
      'female' => t('Female'),
      'none' => t('Neither.'),
      'dont_answer' => t("I don't want to answer"),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIsStudentOptions() {
    return [
      'yes' => t('Yes'),
      'no' => t('No'),
      'graduated' => t('Graduates'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getLaguageSiteOptions() {
    $langcode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();

    return [
      'ja' => t('Japanese'),
      'en' => $langcode === 'ja' ? '英語' : 'English',
      'zh-hans' => t('Chinese'),
    ];
  }

  /**
   * {@inheritdoc.
   */
  public function getNations() {
    return [
      '' => t('- Select -'),
      'AS' => t('American Samoa'),
      'AE' => t('United Arab Emirates'),
      'AR' => t('Argentina'),
      'YE' => t('Yemen'),
      'UK' => t('United Kingdom'),
      'IT' => t('Italy'),
      'IQ' => t('Iraq'),
      'IR' => t('Iran'),
      'IN' => t('India'),
      'ID' => t('Indonesia'),
      'UA' => t('Ukraine'),
      'EG' => t('Egypt'),
      'EE' => t('Estonia'),
      'AU' => t('Australia'),
      'NL' => t('Netherlands'),
      'QA' => t('Qatar'),
      'CA' => t('Canada'),
      'KH' => t('Cambodia'),
      'CO' => t('Colombia'),
      'JM' => t('Jamaica'),
      'SG' => t('Singapore'),
      'CH' => t('Switzerland'),
      'SE' => t('Sweden'),
      'ES' => t('Spain'),
      'LK' => t('Sri Lanka'),
      'SI' => t('Slovenia'),
      'TH' => t('Thailand'),
      'CZ' => t('Czechia'),
      'DE' => t('Germany'),
      'TR' => t('Turkey'),
      'NZ' => t('New Zealand'),
      'NO' => t('Norway'),
      'HU' => t('Hungary'),
      'BD' => t('Bangladesh'),
      'PH' => t('Philippines'),
      'FI' => t('Finland'),
      'BR' => t('Brazil'),
      'FR' => t('France'),
      'BG' => t('Bulgaria'),
      'VN' => t('Vietnam'),
      'PE' => t('Peru'),
      'BE' => t('Belgium'),
      'PL' => t('Poland'),
      'PT' => t('Portugal'),
      'MY' => t('Malaysia'),
      'MM' => t('Myanmar (Burma)'),
      'MX' => t('Mexico'),
      'MA' => t('Morocco'),
      'MN' => t('Mongolia'),
      'LV' => t('Latvia'),
      'LT' => t('Lithuania'),
      'RO' => t('Romania'),
      'RU' => t('Russia'),
      'KR' => t('South Korea'),
      'HK' => t('Hong Kong SAR China'),
      'TW' => t('Taiwan'),
      'CN' => t('China'),
      'ZA' => t('South Africa'),
      'JP' => t('Japan'),
      '0' => t('Other'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getNumberOfApplicationClass(NodeInterface $class) {
    $sql =
      "SELECT COUNT(*) FROM `user_class_field_data` AS uc JOIN `node_field_data` AS nf ON (uc.class = nf.nid AND uc.langcode = nf.langcode) WHERE uc.class = :class_id AND nf.type ='class' AND uc.uid IS NOT NULL";
    $result = \Drupal::database()
      ->query($sql, [':class_id' => $class->id()])
      ->fetchCol();

    return $result ? reset($result) : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getNumberOfPreSurveySubmission(NodeInterface $class) {
    $sql =
      "SELECT COUNT(*) FROM user_class_field_data AS uc JOIN node_field_data AS nf ON (uc.class = nf.nid AND uc.langcode = nf.langcode) WHERE uc.class = :class_id AND nf.type ='class' AND uc.pre_survey IS NOT NULL AND uc.uid IS NOT NULL";
    $result = \Drupal::database()
      ->query($sql, [':class_id' => $class->id()])
      ->fetchCol();

    return $result ? reset($result) : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getPostSurveyData($post_survey) {
    return $this->getPreSurveyData($post_survey);
  }

  /**
   * {@inheritdoc}
   */
  public function getPreSurveyData($pre_survey) {
    if ($pre_survey) {
      $pre_survey_data = $pre_survey->getData();
      $webform = $pre_survey->getWebform();
      $elements_data = $webform->getElementsInitializedFlattenedAndHasValue();

      foreach ($elements_data as $key => $item) {
        if ($item['#options'] ?? FALSE) {
          if (\is_array($pre_survey_data[$key] ?? FALSE)) {
            $element_values = [];

            foreach ($pre_survey_data[$key] as $value) {
              $submit_value = $item['#options'][$value] ?? '';

              if ($submit_value) {
                $element_values[] = $submit_value;
              }
              else {
                $element_values[] = $value;
              }
            }
            $pre_survey_data[$key] = implode("\r\n", $element_values);
          }
          elseif ($item['#options'][$pre_survey_data[$key] ?? ''] ?? '') {
            $pre_survey_data[$key] = $item['#options'][$pre_survey_data[$key]];
          }
        }
      }

      if ((string) $pre_survey_data['i_checked_the_terms_of_use'] === '1') {
        $pre_survey_data['i_checked_the_terms_of_use'] = t('Yes');
      }
      else {
        $pre_survey_data['i_checked_the_terms_of_use'] = t('No');
      }

      return $pre_survey_data;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getStepOptionByLangcode() {
    $langcode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();

    if ($langcode === 'ja' || $langcode === 'zh-hans') {
      $steps = [
        1 => t('Input <br class="sp-only"> of the item'),
        2 => t('Confirm <br class="sp-only"> of the input contents'),
        3 => t('Authentication email <br class="sp-only"> send'),
        4 => t('Member registration <br class="sp-only"> completed'),
      ];
    }
    else {
      $steps = [
        1 => 'Input <br class="sp-only">screen',
        2 => 'Confirmation <br class="sp-only">screen',
        3 => 'Send <br class="sp-only">verification <br class="sp-only">email',
        4 => 'Member <br class="sp-only">registration <br class="sp-only">completed',
      ];
    }

    return $steps;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubmissionPreSurvey(UserClassInterface $userClass) {
    $submission = $userClass->get('pre_survey')->referencedEntities();

    return $submission ? reset($submission) : NULL;
  }

  public function getTimeStampWithTimezone(string $date_value, $is_timestamp = TRUE) {
    return $this->time($date_value, $is_timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function time(string $date_value, $is_timestamp = TRUE) {
    $timestamp = strtotime($date_value);
    $timezone = date_default_timezone_get();
    $userTimezone = new \DateTimeZone(!empty($timezone) ? $timezone : 'GMT');
    $gmtTimezone = new \DateTimeZone('GMT');
    $myDateTime = new \DateTime($timestamp !== FALSE ? date('r', (int) $timestamp) : date('r'), $gmtTimezone);
    $offset = $userTimezone->getOffset($myDateTime);
    $new_date_with_timezone = date(\DATE_ATOM, ($timestamp !== FALSE ? (int) $timestamp : $myDateTime->format('U')) + $offset);

    return $is_timestamp ? strtotime($new_date_with_timezone) : $new_date_with_timezone;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlDashboardWithLanguage($user, $route_name) {
    $langcodeUser = strtolower($user->get('field_language_website')->getString());
    $language = \Drupal::languageManager()->getLanguage($langcodeUser);

    if ($language) {
      return Url::fromRoute($route_name, [], ['language' => $language])->toString();
    }

    return Url::fromRoute($route_name)->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getUserClass(NodeInterface $class, UserInterface $user) {
    $classes = $this->userClassStorage->loadByProperties([
      'class' => $class->id(),
      'uid' => $user->id(),
    ]);

    return $classes ? reset($classes) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserClassByClass(NodeInterface $class, $langcode) {
    if ($langcode) {
      return $this->userClassStorage->loadByProperties([
        'class' => $class->id(),
        'langcode' => $langcode,
      ]);
    }

    return $this->userClassStorage->loadByProperties(['class' => $class->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserClasses(UserInterface $user) {
    // Filter cancelled courses
    $tthis = $this;
    return array_filter($this->userClassStorage->loadByProperties(['uid' => $user->id()]), function ($user_class) use ($tthis) {
      $user_courses = array_filter(\Drupal::service('lms_user.manager')->getUserCoursesByUserClass($user_class), function ($course) {
        return $course->isPublished();
      });
      return !!$user_courses;
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getUserCourse(ProductInterface $course, UserInterface $user) {
    $courses = $this->userCourseStorage->loadByProperties([
      'course' => $course->id(),
      'uid' => $user->id(),
      'status' => 1,
    ]);

    return $courses ? reset($courses) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserCoursePostSurvey($course, $user_id) {
    $courses = $this->userCourseStorage->loadByProperties([
      'course' => $course->id(),
      'uid' => $user_id,
      'status' => 1,
    ]);

    return $courses ? reset($courses) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserCourses(UserInterface $user) {
    return $this->userCourseStorage->loadByProperties([
      'uid' => $user->id(),
      'status' => 1,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserCoursesByUserClass(UserClassInterface $userClass) {
    return $this->userCourseStorage->loadByProperties([
      'class' => $userClass->id(),
      'status' => 1,
    ]);
  }

  public function getUserCourseWithLanguageCode(UserCourseInterface $userCourse, $langcode) {
    return $userCourse->hasTranslation($langcode) ? $userCourse->getTranslation($langcode) : $userCourse;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserInformation($user) {
    if ($user) {
      $nation_options = $this->getNations();
      $gender_options = $this->getGenderOptions();
      $first_language_options = $this->getFirstLanguages();
      $language_site_options = $this->getLaguageSiteOptions();
      $is_student_options = $this->getIsStudentOptions();
      $user_info['full_name'] = $user->get('field_full_name')->value;
      $user_info['pronounce_name'] = $user->get('field_pronounce_name')->value;
      $user_info['mail'] = $user->getEmail();
      $user_info['birthday'] = $user->get('field_birthday')->value;
      $user_info['gender'] = $user->get('field_gender')->value ? $gender_options[$user->get('field_gender')->value] : NULL;
      $nationality = $user->get('field_nationality')->value;

      if ($nationality === '0') {
        $user_info['nationality'] = $user->get('field_other_nation')->value;
      }
      else {
        $user_info['nationality'] = $nation_options[$nationality];
      }
      $first_language = $user->get('field_first_language')->value;

      if ($first_language === '0') {
        $user_info['first_language'] = $user->get('field_other_first_language')->value;
      }
      else {
        $user_info['first_language'] = $first_language_options[$first_language];
      }
      $user_info['language_website'] = $user->get('field_language_website')->value ? $language_site_options[$user->get('field_language_website')->value] : NULL;
      $user_info['still_student'] = $user->get('field_is_student')->value ? $is_student_options[$user->get('field_is_student')->value] : NULL;

      return $user_info;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function hasUserClass(NodeInterface $class, UserInterface $user) {
    $classes = $this->userClassStorage->loadByProperties([
      'class' => $class->id(),
      'uid' => $user->id(),
    ]);

    return !empty($classes);
  }

  /**
   * {@inheritdoc}
   */
  public function hasUserCourse(ProductInterface $course, UserInterface $user) {
    $courses = $this->userCourseStorage->loadByProperties([
      'course' => $course->id(),
      'uid' => $user->id(),
      'status' => 1,
    ]);

    return !empty($courses);
  }

  /**
   * {@inheritdoc}
   */
  public function isSubmittedPostSurvey(ProductInterface $course, UserInterface $user) {
    $user_course = $this->getUserCourse($course, $user);

    if ($user_course) {
      $check_post_survey = $user_course->get('post_survey')->referencedEntities();

      if (!empty($check_post_survey)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isSubmittedPreSurvey(NodeInterface $class, UserInterface $user) {
    $user_class = $this->getUserClass($class, $user);

    if ($user_class) {
      $check_pre_survey = $user_class->get('pre_survey')->referencedEntities();

      if (!empty($check_pre_survey)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function reminderPresurvey() {
    $debug = $GLOBALS['debug'][__FUNCTION__] ?? FALSE;
    $debug_mail = $GLOBALS['debug'][__FUNCTION__ . '_mail'] ?? FALSE;
    $debug_save = $GLOBALS['debug'][__FUNCTION__ . '_save'] ?? FALSE;
    $current_time = \Drupal::time()->getRequestTime();
    $lmsWebform = \Drupal::service('lms_mail.lms_webform');

    // @TODO: Better query by excluding pre_survey and including class
    $user_ids = \Drupal::entityQuery('user')
      ->condition('status', 1)
      ->condition('roles', 'student')
      // Ensure user has class
      ->condition('target_id.referenced_by:uid:user_class.name', '', '!=')
      ->execute();
    $users = User::loadMultiple($user_ids);
    $tracked = [];

    foreach ($users as $user) {
      $user_courses = $this->userCourseStorage->loadByProperties([
        'uid' => $user->id(),
        'status' => 1,
      ]);

      if ($user_courses) {
        $course_pre_survey = [];

        foreach ($user_courses as $user_course) {
          // 3. Send once.
          $uclass = $user_course->get('class')->getValue()[0]['target_id'] ?? FALSE;

          if (!$uclass) {
            continue;
          }
          $uclass = UserClass::load($uclass);

          if (!$uclass || (string) $uclass->getCustom('pre_survey_sent') === '1') {
            continue;
          }

          $course_value = $user_course->get('course')->getValue();

          if ($course_value) {
            $course_id = $course_value[0]['target_id'];
            $course = Product::load($course_id);

            // 2. Only send when not submitted
            $class = Node::load($uclass->get('class')->getValue()[0]['target_id'] ?? FALSE);

            if (!$class || $this->isSubmittedPreSurvey($class, $user)) {
              continue;
            }

            if ($course) {
              $event_range_value = $course->get('field_day_of_the_event')->getValue();

              if ($event_range_value) {
                $event_range = reset($event_range_value);
                $event_range_start = strtotime($event_range['value']);
                // 1. Send 3 days before course starting
                $compare = \Drupal::config('lms_mail.settings')->get('reminder') ?? 3 * 24 * 3600;
                if ($debug || ($event_range_start - $current_time >= 0 && $event_range_start - $current_time <= $compare)) {
                  $tracked[] = $uclass;
                  $course_pre_survey[] = $course;
                }
              }
            }
          }
        }

        if (\count($course_pre_survey) > 0) {
          if ($debug_mail) {
            \Drupal::messenger()->addMessage(
              t('Sending mail @ret', [
                '@ret' => print_r([\count($course_pre_survey), $user->id()], 1),
              ]),
              'status'
            );
            $result = TRUE;
          }
          else {
            $result = $lmsWebform->sendPreSurveyReminder($course_pre_survey, $user);
          }

          if (!$result) {
            \Drupal::logger('webform_submission')->error(t("Can't send mail to student %name to reminder presurvey"), [
              '%name' => $user->get('field_full_name')->getString(),
            ]);
          }
          else {
            // Track sent to user course @TODO: Should be unique yet?
            foreach ($tracked as $uclass) {
              $uclass->setCustom('pre_survey_sent', 1);

              if ($debug_save) {
                \Drupal::messenger()->addMessage(
                  t('Update sent to class @ret', [
                    '@ret' => print_r([$uclass->id()], 1),
                  ]),
                  'status'
                );
              }
              else {
                $uclass->save();
              }
              \Drupal::logger('lms_user')->notice(
                t('Sent reminder to @cid @uid', [
                  '@cid' => $uclass->id(),
                  '@uid' => $user->id(),
                ])
              );
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setUrlWithDefaultLanguage($host, $url) {
    $current_langcode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
    $url_langcode = str_replace('/' . $current_langcode, '', $url);

    return !$host ? $url_langcode : $host . $url_langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function setUrlWithLanguage($host, $url) {
    $currentUser = \Drupal::currentUser();
    $user = User::load($currentUser->id());
    $defaultSiteLanguage = \Drupal::languageManager()
      ->getDefaultLanguage()
      ->getId();
    $currentSiteLanguage = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
    $langcodeUser = strtolower($user->get('field_language_website')->getString());
    $url_langcode = str_replace('/' . $currentSiteLanguage, '', $url);

    if ($url_langcode[0] !== '/') {
      $url_langcode = '/' . $url_langcode;
    }

    if ($langcodeUser && $langcodeUser !== $defaultSiteLanguage) {
      $url_langcode = '/' . $langcodeUser . $url_langcode;
    }
    elseif (!$langcodeUser && $defaultSiteLanguage !== $currentSiteLanguage) {
      $url_langcode = '/' . $currentSiteLanguage . $url_langcode;
    }

    return !$host ? $url_langcode : $host . $url_langcode;
  }

  public function updateCourseAppNumber($course) {
    $lang_code = $this->languageManager->getCurrentLanguage()->getId();
    $translation = $course;
    if ($course->hasTranslation($lang_code)) {
      $translation = $course->getTranslation($lang_code);
    }
    $count = \Drupal::entityQuery('user_course')
      ->condition('course', $course->id())
      ->condition('status', 1)
      ->count()
      ->execute();
    $translation->set('field_application_number', $count);
    $translation->save();
  }

  /**
   * {@inheritdoc}
   */
  public function updateAppNumber($user_course) {
    if ($user_course->getEntityTypeId() !== 'user_course') {
      return;
    }
    $courses = $user_course->get('course')->referencedEntities();
    if (!$courses) {
      return;
    }

    if (isset($courses)) {
      foreach ($courses as $course) {
        $this->updateCourseAppNumber($course);
      }
    }
  }

}
