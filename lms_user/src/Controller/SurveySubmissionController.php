<?php

namespace Drupal\lms_user\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lms_commerce\CourseManagerInterface;
use Drupal\lms_commerce\CourseStatusInterface;
use Drupal\lms_user\UserManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class SurveySubmissionController extends ControllerBase {
  /**
   * @var \Drupal\lms_user\UserManagerInterface
   */
  protected $userManager;

  /**
   * @var \Drupal\lms_commerce\CourseManagerInterface
   */
  protected $courseManager;

  /**
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * SurveySubmissionController constructor.
   * @param \Drupal\lms_user\UserManagerInterface $user_manager
   * @param \Drupal\lms_commerce\CourseManagerInterface $course_manager
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   */
  public function __construct(
    UserManagerInterface $user_manager,
    CourseManagerInterface $course_manager,
    Request $request,
    LanguageManagerInterface $language_manager
  ) {
    $this->userManager = $user_manager;
    $this->courseManager = $course_manager;
    $this->request = $request;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lms_user.manager'),
      $container->get('lms_commerce.course_manager'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('language_manager')
    );
  }

  /**
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function preSurvey(RouteMatchInterface $route_match) {
    $user = $this->currentUser();
    $destination = $this->request->getPathInfo();
    if ($user->isAuthenticated()) {
      /** @var \Drupal\lms_user\Entity\UserClass $userClass */
      $userClass = $route_match->getParameter('user_class');
      $uid = $userClass->get('uid')->getString();
      if ($uid != $user->id()) {
        return [
          '#theme' => 'lms_user_pre_survey_message',
          '#account' => $user,
          '#message' => t(
            'You don\'t have permission to access the pre-survey'
          ),
        ];
      }
      $preSurvey = \Drupal::service('lms_user.manager')->getClassPreSurvey(
        $userClass
      );
      $form_submission = $this->entityTypeManager()
        ->getStorage('webform')
        ->load($preSurvey->id());
      return $this->entityTypeManager()
        ->getViewBuilder('webform')
        ->view($form_submission);
    }
    else {
      return $this->redirect(
        'user.login',
        [],
        ['query' => ['destination' => $destination]]
          );
    }
  }

  /**
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function postSurvey(RouteMatchInterface $route_match) {
    $user = $this->currentUser();
    $destination = $this->request->getPathInfo();
    if ($user->isAuthenticated()) {
      /** @var \Drupal\commerce_product\Entity\ProductInterface $course */
      $course = $route_match->getParameter('course');
      $status_course = $this->courseManager->getCourseStatus($course);
      $field_day_of_the_event = $course
        ->get('field_day_of_the_event')
        ->first()
        ->getValue();

      $day_of_the_event_start = $this->userManager->getTimeStampWithTimezone(
        $field_day_of_the_event['value']
      );

      $current_time_tokyo = new \DateTime(
        'now',
        new \DateTimeZone(date_default_timezone_get())
      );
      $current_timestamp_tokyo = $current_time_tokyo->getTimestamp();

      $is_test = (\Drupal::currentUser()->id() == '1' && $_GET['debug'] == 1);
      if ($status_course == CourseStatusInterface::COMPLETED && !$is_test) {
        return [
          '#theme' => 'lms_user_post_survey_message',
          '#account' => $user,
          '#message' => t('You submitted the post-survey already'),
        ];
      }
      elseif (
        $status_course == CourseStatusInterface::CLOSED or
        $status_course == CourseStatusInterface::RECRUITING or
        $status_course == CourseStatusInterface::APPLIED and
          $day_of_the_event_start > $current_timestamp_tokyo
      ) {
        return [
          '#theme' => 'lms_user_post_survey_message',
          '#account' => $user,
          '#message' => t('You can\'t access to the post-survey'),
        ];
      }
      elseif (
        $is_test || (
        $status_course == CourseStatusInterface::FINISHED or
        $status_course == CourseStatusInterface::APPLIED and
          $day_of_the_event_start <= $current_timestamp_tokyo
        )
      ) {
        $postSurveys = $course->get('field_post_survey')->referencedEntities();
        if (empty($postSurveys)) {
          return [
            '#theme' => 'lms_user_post_survey_message',
            '#account' => $user,
          ];
        }
        else {
          /** @var \Drupal\webform\Entity\Webform $postSurvey */
          $postSurvey = reset($postSurveys);
          $postSurveyStatus = $course
            ->get('field_post_survey')
            ->first()
            ->getValue();
          $is_closed = FALSE;
          if ($postSurveyStatus['status'] == 'scheduled') {
            $post_survey_close = $this->userManager->getTimeStampWithTimezone(
              $postSurveyStatus['close']
            );
            $post_survey_open = $this->userManager->getTimeStampWithTimezone(
              $postSurveyStatus['open']
            );
            if (
              $post_survey_close < $current_timestamp_tokyo or
              $post_survey_open > $current_timestamp_tokyo
            ) {
              $is_closed = TRUE;
            }
            if (
              $status_course == CourseStatusInterface::APPLIED and
              $post_survey_open > $day_of_the_event_start and
              $post_survey_open > $current_timestamp_tokyo
            ) {
              $is_closed = TRUE;
            }
          }
          if (
            !$postSurvey->isOpen() or
            $postSurveyStatus['status'] == 'closed' or
            $is_closed
          ) {
            return [
              '#theme' => 'lms_user_post_survey_message',
              '#account' => $user,
              '#message' => t('The post-survey link is unavailable'),
            ];
          }
          else {
            $postSurvey_id = $postSurvey->id();
            $form_submission = $this->entityTypeManager()
              ->getStorage('webform')
              ->load($postSurvey_id);
            return $this->entityTypeManager()
              ->getViewBuilder('webform')
              ->view($form_submission);
          }
        }
      }
      else {
        return $this->redirect('entity.commerce_product.canonical', [
          'commerce_product' => $course->id(),
        ]);
      }
    }
    else {
      return $this->redirect(
        'user.login',
        [],
        ['query' => ['destination' => $destination]]
          );
    }
  }

  /**
   * Access presurvey callback.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return \Drupal\Core\Access\AccessResult|\Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultNeutral
   */
  public function checkPreSurveyAccess(
    RouteMatchInterface $route_match,
    AccountInterface $account
  ) {
    $userClass = $route_match->getParameter('user_class');
    $preSurveys = $userClass->get('pre_survey')->referencedEntities();
    return AccessResult::allowedIf(empty($preSurveys));
  }

  /**
   * Check access to post survey page.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return \Drupal\Core\Access\AccessResult|\Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultNeutral
   */
  public function checkPostSurveyAccess(
    RouteMatchInterface $route_match,
    AccountInterface $account
  ) {
    $userCourse = $route_match->getParameter('user_course');
    $postSurveys = $userCourse->get('post_survey')->referencedEntities();
    return AccessResult::allowedIf(empty($postSurveys));
  }

}
