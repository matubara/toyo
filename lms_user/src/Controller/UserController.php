<?php

namespace Drupal\lms_user\Controller;

use Drupal\Core\Locale\CountryManager;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Drupal\user\UserStorageInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\lms_user\Form\UserOneTimeLoginForm;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\lms_commerce\CourseManagerInterface;
use Drupal\lms_mail\Mail\LmsUserMail;
use Drupal\lms_user\Form\UserLogoutForm;
use Drupal\lms_user\UserManagerInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserController extends ControllerBase {
  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTemp;

  /**
   * @var \Drupal\lms_user\UserManagerInterface
   */
  protected $userManager;

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $lmsUserMail;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * @var \Drupal\lms_commerce\CourseManagerInterface
   */
  protected $courseManager;

  /**
   * UserController constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Drupal\Component\Datetime\TimeInterface $time
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $private_temp
   * @param \Drupal\lms_user\UserManagerInterface $user_manager
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param \Drupal\lms_mail\Mail\LmsUserMail $lms_user_mail
   * @param \Drupal\user\UserStorageInterface $userStorage
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   * @param \Drupal\lms_commerce\CourseManagerInterface $course_manager
   */
  public function __construct(
    LoggerInterface $logger,
    TimeInterface $time,
    PrivateTempStoreFactory $private_temp,
    UserManagerInterface $user_manager,
    RouteMatchInterface $route_match,
    LmsUserMail $lms_user_mail,
    UserStorageInterface $userStorage,
    DateFormatterInterface $dateFormatter,
    CourseManagerInterface $course_manager
  ) {
    $this->logger = $logger;
    $this->time = $time;
    $this->privateTemp = $private_temp;
    $this->userManager = $user_manager;
    $this->routeMatch = $route_match;
    $this->lmsUserMail = $lms_user_mail;
    $this->userStorage = $userStorage;
    $this->dateFormatter = $dateFormatter;
    $this->courseManager = $course_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory')->get('user'),
      $container->get('datetime.time'),
      $container->get('tempstore.private'),
      $container->get('lms_user.manager'),
      $container->get('current_route_match'),
      $container->get('lms_mail.lms_user'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('date.formatter'),
      $container->get('lms_commerce.course_manager')
    );
  }

  /**
   * Render dashboard page.
   */
  public function dashboard() {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $langcode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
    $account = User::load($this->currentUser()->id());
    $userClasses = $this->userManager->getUserClasses($account);
    $edit_profile_url = Url::fromRoute('lms_user.edit_profile')->toString();
    $how_to_url = $this->config('lms_user.settings')->get('how_to_page_url');
    $how_to_url = Url::fromUserInput($how_to_url)->toString();
    $flag_pre = TRUE;
    $class_title = [];
    foreach ($userClasses as $key => $userClass) {
      $class = $this->userManager->getClassFromUserClass($userClass);
      if ($class) {
        $class = $class->hasTranslation($langcode)
          ? $class->getTranslation($langcode)
          : $class;
        $class_title['title_' . $key] = $class->label();
        $isSubmitted = $this->userManager->isSubmittedPreSurvey(
          $class,
          $account
        );
        if ($isSubmitted == FALSE) {
          $flag_pre = FALSE;
        }
      }
      else {
        unset($userClasses[$key]);
      }
    }
    $find_class_link = Url::fromRoute('view.lms_class.page_1')->toString();
    return [
      '#theme' => 'lms_user_dashboard',
      '#account' => $account,
      '#find_class_link' => $find_class_link,
      '#user_classes' => $userClasses,
      '#edit_profile_url' => $edit_profile_url,
      '#title_class' => $class_title,
      '#flag_pre' => $flag_pre,
      '#how_to_url' => $how_to_url,
    ];
  }

  /**
   * Course list page.
   */
  public function courseList(RouteMatchInterface $route_match) {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $langcode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
    $user_class = $route_match->getParameter('user_class');
    $classes = $user_class->get('class')->referencedEntities();
    $user_courses = $user_class->get('user_courses')->referencedEntities();
    $course_title = [];
    $course_time = [];
    foreach ($user_courses as $key => $user_course) {
      /** @var \Drupal\commerce_product\Entity\ProductInterface[] $courses */
      $courses = FALSE;
      if ($user_course->isPublished()) {
        $courses = $user_course->get('course')->referencedEntities();
      }
      if ($courses) {
        $course = reset($courses);
        $course = $course->hasTranslation($langcode)
          ? $course->getTranslation($langcode)
          : $course;
        $course_title['title_' . $key] = $course->label();
        $course_time['day_' . $key] = [
          '#value' => $this->courseManager->getCourseEndDateFormat($course),
        ];
        $course_time['time_' . $key] = [
          '#value' => $this->courseManager->getCourseEndDateRangeFormat(
            $course
          ),
        ];
      }
      else {
        unset($user_courses[$key]);
      }
    }
    $class = reset($classes);
    $class = $class->hasTranslation($langcode)
      ? $class->getTranslation($langcode)
      : $class;
    $title_class = $class->label();
    return [
      '#theme' => 'lms_user_courses',
      '#user_class' => $user_class,
      '#user_courses' => $user_courses,
      '#title_course' => $course_title,
      '#course_time' => $course_time,
      '#title_class' => $title_class,
    ];
  }

  /**
   * Student Detail Page.
   */
  public function studentDetail(UserInterface $user) {
    $userClasses = $this->userManager->getUserClasses($user);
    $user_courses = [];
    $course_title = [];
    $langcode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
    foreach ($userClasses as $userClass) {
      $userCourses = $userClass->get('user_courses')->referencedEntities();
      foreach ($userCourses as $key => $course) {
        if ($course->isPublished()) {
          $user_courses[] = $course;
          $course_id = $course->id();
          $course = $this->userManager->getUserCourseWithLanguageCode(
              $course,
              $langcode
          );
          $course_title['title_' . $course_id] = $course->label();
        }

      }
    }

    $allowed_values = [
      'field_gender' => $user->get('field_gender')->getSetting('allowed_values'),
    ];
    $country_code = $user->get('field_nationality')->getString();
    $nations = CountryManager::getStandardList();
    /** Add UK to the nations list */
    $nations['UK'] = t('United Kingdom');
    $country = NULL;
    if (!$country_code || in_array((string) $country_code, ['other', '0'], TRUE)) {
      $country = $user->get('field_other_nation')->getString();
    }
    else {
      $country = $nations[$country_code] ?? 'N/A';
    }

    return [
      '#theme' => 'lms_student_detail',
      '#user' => $user,
      '#user_courses' => $user_courses,
      '#title_course' => $course_title,
      '#allowed_values' => $allowed_values,
      '#country' => $country,
    ];
  }

  /**
   * Verify registered email.
   *
   * @param \Drupal\user\Entity\User $user
   * @param int $timestamp
   * @param string $hash
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function registerVerify(User $user, int $timestamp, string $hash) {
    $current = $this->time->getRequestTime();
    $timeout = strtotime('+ 1 day', $current) - $current;
    if ($user->getLastLoginTime() || $current - $timestamp > $timeout) {
      throw new NotFoundHttpException();
    }
    elseif (
      $timestamp <= $current &&
      hash_equals($hash, user_pass_rehash($user, $timestamp))
    ) {
      $user->set('status', 1);
      $user->save();
      user_login_finalize($user);
      $this->logger->notice(
        'User %name used one-time verify link at time %timestamp.',
        [
          '%name' => $user->getDisplayName(),
          '%timestamp' => $timestamp,
        ]
          );
      // $this->messenger()->addStatus($this->t('You have successfully activated your account'));
      $tempStore = $this->privateTemp->get('lms_user_register');
      $token = Crypt::randomBytesBase64(55);
      $tempStore->set('register_completed_' . $user->id(), $token);

      $registerSuccess = $this->lmsUserMail->sendUserRegisterCompleted(
            $user,
            $user->getEmail()
          );
      if (!$registerSuccess) {
        \Drupal::messenger()->addError(
          t("Error: Can't send mail register completed")
        );
      }
      $registerSuccessToAdmin = $this->lmsUserMail->sendUserRegisterCompletedToAdmin(
            $user
          );
      if (!$registerSuccessToAdmin) {
        $this->logger->error(
          'Can\'t send mail member %name registration to admin',
          ['%name' => $user->getDisplayName()]
        );
      }

      return $this->redirect('lms_user.register', ['step' => 'completed']);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

  public function resetPasswordCompleted() {
    $markup =
      '<div class="password-completed">
              <div>
                <div>' .
      t(
        'Password reset instructions have been sent to your registered address.'
      ) .
      '</div>
                <p>
                 ' .
      t(
        'Password reset instructions have been sent to your registered address.'
      ) .
      '<br>
                  ' .
      t('Please check your email.') .
      '
                </p>
              </div>
            </div>';

    return [
      '#title' => t('Reset Password'),
      '#markup' => $markup,
    ];
  }

  public function resetPass(Request $request, $uid, $timestamp, $hash) {
    $account = $this->currentUser();
    // When processing the one-time login link, we have to make sure that a user
    // isn't already logged in.
    if ($account->isAuthenticated()) {
      // The current user is already logged in.
      if ($account->id() == $uid) {
        user_logout();
        // We need to begin the redirect process again because logging out will
        // destroy the session.
        return $this->redirect('user.reset', [
          'uid' => $uid,
          'timestamp' => $timestamp,
          'hash' => $hash,
        ]);
      }
      // A different user is already logged in on the computer.
      else {
        /** @var \Drupal\user\UserInterface $reset_link_user */
        if ($reset_link_user = $this->userStorage->load($uid)) {
          $this->messenger()->addWarning(
            $this->t(
              'Another user (%other_user) is already logged into the site on this computer, but you tried to use a one-time link for user %resetting_user. Please <a href=":logout">log out</a> and try using the link again.',
              [
                '%other_user' => $account->getAccountName(),
                '%resetting_user' => $reset_link_user->getAccountName(),
                ':logout' => Url::fromRoute('user.logout')->toString(),
              ]
            )
          );
        }
        else {
          // Invalid one-time link specifies an unknown user.
          $this->messenger()->addError(
            $this->t('The one-time login link you clicked is invalid.')
                  );
        }
        return $this->redirect('<front>');
      }
    }
    $session = $request->getSession();
    $session->set('pass_reset_hash', $hash);
    $session->set('pass_reset_timeout', $timestamp);
    $session->set('uid', $uid);
    return $this->redirect('lms_user.password.onetime');
  }

  public function getResetPassForm(Request $request) {
    user_logout();
    $session = $request->getSession();
    $timestamp = $session->get('pass_reset_timeout');
    $hash = $session->get('pass_reset_hash');
    $uid = $session->get('uid');
    // As soon as the session variables are used they are removed to prevent the
    // hash and timestamp from being leaked unexpectedly. This could occur if
    // the user does not click on the log in button on the form.
    $session->remove('pass_reset_timeout');
    $session->remove('pass_reset_hash');
    $session->remove('uid');
    if (!$hash || !$timestamp || !$uid) {
      throw new AccessDeniedHttpException();
    }
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->load(460);
    if ($user === NULL || !$user->isActive()) {
      // Blocked or invalid user ID, so deny access. The parameters will be in
      // the watchdog's URL for the administrator to check.
      throw new AccessDeniedHttpException();
    }
    // Time out, in seconds, until login URL expires.
    $timeout = $this->config('user.settings')->get('password_reset_timeout');
    if ($timestamp + $timeout < \Drupal::time()->getCurrentTime()) {
      $expiration_date = $this->dateFormatter->format($timestamp + $timeout);
    }
    else {
      $expiration_date = NULL;
    }
    return \Drupal::formBuilder()->getForm(
      UserOneTimeLoginForm::class,
      $user,
      $expiration_date,
      $timestamp,
      $hash
    );
  }

  /**
   * @return array
   */
  public function logoutPage() {
    return \Drupal::formBuilder()->getForm(UserLogoutForm::class);
  }

  /**
   * @return \Drupal\Core\Access\AccessResult|Drupal\Core\Access\AccessResultAllowed|Drupal\Core\Access\AccessResultNeutral
   */
  public function checkAccessUpdateApplicationNumber() {
    $current_user = User::load(\Drupal::currentUser()->id());
    return AccessResult::allowedIf(
      $current_user->hasRole('administrator') ||
        $current_user->hasRole('lms_admin')
    );
  }

  public function exportStudentList() {
    $response = new Response();
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');
    $response->headers->set('Content-Type', 'application/vnd.ms-excel');
    $response->headers->set('Content-Disposition', 'attachment; filename=Student.csv');

    $userStorange = $this->entityTypeManager()->getStorage('user');
    $student_ids = $userStorange->getQuery()
      ->condition('roles', 'student', '=')
      ->sort('field_member_id', "ASC")
      ->execute();
    $students = $userStorange->loadMultiple($student_ids);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $title = t('Student list')->render();
    $sheet->setTitle($title);
    $sheet->setCellValue('A1', 'No');
    $sheet->setCellValue('B1', '会員番号');
    $sheet->setCellValue('C1', '名前');
    $sheet->setCellValue('D1', 'メールアドレス');
    $sheet->setCellValue('E1', 'ステータス');
    $count = 1;
    foreach ($students as $student) {
      $count++;
      $sheet->setCellValue('A' . $count, $count - 1);
      $sheet->setCellValue('B' . $count, $student->get('field_member_id')->getString());
      $sheet->setCellValue('C' . $count, $student->get('field_full_name')->getString());
      $sheet->setCellValue('D' . $count, $student->get('mail')->getString());
      $sheet->setCellValue('E' . $count, $student->get('status')->value == 1 ? 'Active' : 'Blocked');
    }
    $write = new Csv($spreadsheet);
    ob_start();
    $write->save('php://output');
    $content = ob_get_clean();

    // Memory cleanup.
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    $response->setContent($content);
    return $response;
  }

  /**
   * @return Response
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function exportStudentList2() {
    $response = new Response();
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');
    $response->headers->set('Content-Type', 'application/vnd.ms-excel');
    $response->headers->set('Content-Disposition', 'attachment; filename=Student.csv');

    $userStorange = $this->entityTypeManager()->getStorage('user');
    $student_ids = $userStorange->getQuery()
      ->condition('roles', 'student', '=')
      ->sort('field_member_id', "ASC")
      ->execute();
    $students = $userStorange->loadMultiple($student_ids);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $title = t('Student list')->render();
    $sheet->setTitle($title);
    $sheet->setCellValue('A1', 'No');
    $sheet->setCellValue('B1', '会員番号');
    $sheet->setCellValue('C1', '名前');
    $sheet->setCellValue('D1', 'メールアドレス');
    $sheet->setCellValue('E1', '生年月日');
    $sheet->setCellValue('F1', '国籍');
    $sheet->setCellValue('G1', 'その他国籍');
    $sheet->setCellValue('H1', '母語');
    $sheet->setCellValue('I1', 'その他母語');
    $sheet->setCellValue('J1', '言語選択');
    $sheet->setCellValue('K1', '東洋大学生ですか？');
    $sheet->setCellValue('L1', '会員登録日');
    $sheet->setCellValue('M1', 'Active★');
    $sheet->setCellValue('N1', '受講回数');
    $count = 1;
    foreach ($students as $student) {
      $count++;
      $sheet->setCellValue('A' . $count, $count - 1);
      $sheet->setCellValue('B' . $count, $student->get('field_member_id')->getString());
      $sheet->setCellValue('C' . $count, $student->get('field_full_name')->getString());
      $sheet->setCellValue('D' . $count, $student->get('mail')->getString());
      $sheet->setCellValue('E' . $count, $student->get('field_birthday')->getString());
      $sheet->setCellValue('F' . $count, $student->get('field_nationality')->getString());
      $sheet->setCellValue('G' . $count, $student->get('field_other_nation')->getString());
      $sheet->setCellValue('H' . $count, $student->get('field_first_language')->getString());
      $sheet->setCellValue('I' . $count, $student->get('field_other_first_language')->getString());
      $sheet->setCellValue('J' . $count, $student->get('field_language_website')->getString());
      $sheet->setCellValue('K' . $count, $student->get('field_is_student')->getString());
      $created = (new \DateTime())->setTimestamp($student->get('created')->value)->setTimezone(new \DateTimeZone('UTC'))->format(\DateTime::RFC3339);
      $sheet->setCellValue('L' . $count, $created);
      $sheet->setCellValue('M' . $count, $student->get('status')->value == 1 ? 'Active' : 'Blocked');
      $sheet->setCellValue('N' . $count, $this->getTotalAttendCourse($student->get('field_member_id')->getString()));
    }
    $write = new Csv($spreadsheet);
    ob_start();
    $write->save('php://output');
    $content = ob_get_clean();

    // Memory cleanup.
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    $response->setContent($content);
    return $response;
  }

  protected function getTotalAttendCourse($field_member_id_value = 0){
    $sql = <<< EOF
    SELECT
      user__field_member_id.field_member_id_value
      , Count(ucourse_data.user_course_id)
    FROM
      users_field_data users_field_data
      LEFT JOIN user_course_field_data ucourse_data ON users_field_data.uid = ucourse_data.uid
      AND ucourse_data.status = '1'
      INNER JOIN user__roles user__roles ON users_field_data.uid = user__roles.entity_id
      AND user__roles.deleted = '0'
      LEFT JOIN user__field_member_id user__field_member_id ON users_field_data.uid = user__field_member_id.entity_id
      AND user__field_member_id.deleted = '0'
    WHERE
      ((user__roles.roles_target_id = 'student'))
      AND (users_field_data.uid != '0')
      AND (user__field_member_id.field_member_id_value = '{$field_member_id_value}')
    GROUP BY user__field_member_id.field_member_id_value
;
EOF;
    $result = \Drupal::database()
      ->query($sql)
      ->fetchField(1);
    return $result;
  }
}
