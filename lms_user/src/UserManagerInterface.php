<?php

namespace Drupal\lms_user;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\lms_user\Entity\UserClassInterface;
use Drupal\lms_user\Entity\UserCourseInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Drupal\webform\WebformSubmissionInterface;

interface UserManagerInterface {

  /**
   * @param \Drupal\user\UserInterface $user
   *
   * @return \Drupal\lms_user\Entity\UserClassInterface[]
   */
  public function getUserClasses(UserInterface $user);

  /**
   * Return name of user submission pre-survey.
   *
   * @param \Drupal\lms_user\Entity\UserClassInterface $userClass
   *
   * @return mixed
   */
  public function getEmailUserClass(UserClassInterface $userClass);

  /**
   * @param \Drupal\node\NodeInterface $class
   * @param \Drupal\user\UserInterface $user
   *
   * @return \Drupal\lms_user\Entity\UserClassInterface|null
   */
  public function getUserClass(NodeInterface $class, UserInterface $user);

  /**
   * Check user is submitted pre-survey class.
   *
   * @param \Drupal\node\NodeInterface $class
   * @param \Drupal\user\UserInterface $user
   *
   * @return true|false
   */
  public function isSubmittedPreSurvey(NodeInterface $class, UserInterface $user);

  /**
   * Check user is submiited post survey.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $course
   * @param \Drupal\user\UserInterface $user
   *
   * @return true|false
   */
  public function isSubmittedPostSurvey(ProductInterface $course, UserInterface $user);

  /**
   * @param \Drupal\lms_user\Entity\UserClassInterface $userClass
   *
   * @return \Drupal\webform\WebformInterface|null
   */
  public function getClassPreSurvey(UserClassInterface $userClass);

  /**
   * @param \Drupal\lms_user\Entity\UserClassInterface $userClass
   *
   * @return mixed
   */
  public function getClassEndDateRangeFormat(NodeInterface $class);

  /**
   * Return end of course.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $course
   *
   * @return mixed
   */
  public function getCourseEndDateRangeFormat(ProductInterface $course);

  /**
   * @param \Drupal\lms_user\Entity\UserClassInterface $userClass
   *
   * @return \Drupal\webform\WebformInterface|null
   */
  public function getSubmissionPreSurvey(UserClassInterface $userClass);

  /**
   * @param \Drupal\lms_user\Entity\UserClassInterface $userClass
   *
   * @return \Drupal\node\NodeInterface|null
   */
  public function getClassFromUserClass(UserClassInterface $userClass);

  /**
   * @param \Drupal\lms_user\Entity\UserCourseInterface $userCourse
   *
   * @return \Drupal\webform\WebformInterface|null
   */
  public function getCoursePostSurvey(UserCourseInterface $userCourse);

  /**
   * @param \Drupal\lms_user\Entity\UserCourseInterface $userCourse
   * @param $langcode
   *
   * @return \Drupal\lms_user\Entity\UserCourseInterface|null
   */
  public function getUserCourseWithLanguageCode(UserCourseInterface $userCourse, $langcode);

  /**
   * @param \Drupal\lms_user\Entity\UserCourseInterface $userCourse
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface|null
   */
  public function getCourseFromUserCourse(UserCourseInterface $userCourse);

  /**
   * @param \Drupal\node\NodeInterface $class
   * @param \Drupal\user\UserInterface $user
   *
   * @return bool
   */
  public function hasUserClass(NodeInterface $class, UserInterface $user);

  /**
   * @param \Drupal\node\NodeInterface $class
   * @param \Drupal\user\UserInterface $user
   *
   * @return \Drupal\lms_user\Entity\UserClassInterface
   */
  public function createUserClass(NodeInterface $class, UserInterface $user);

  /**
   * @param \Drupal\user\UserInterface $user
   *
   * @return \Drupal\lms_user\Entity\UserCourseInterface[]
   */
  public function getUserCourses(UserInterface $user);

  /**
   * @param \Drupal\lms_user\Entity\UserClassInterface $userClass
   *
   * @return mixed
   */
  public function getUserCoursesByUserClass(UserClassInterface $userClass);

  /**
   * Return list user class of class.
   *
   * @param \Drupal\node\NodeInterface $class
   * @param $langcode
   *
   * @return mixed
   */
  public function getUserClassByClass(NodeInterface $class, $langcode);

  /**
   * Return email of user submit post-survey.
   *
   * @param \Drupal\lms_user\Entity\UserCourseInterface $userCourse
   *
   * @return mixed
   */
  public function getEmailUserCourse(UserCourseInterface $userCourse);

  /**
   * @param \Drupal\commerce_product\Entity\ProductInterface $course
   * @param \Drupal\user\UserInterface $user
   *
   * @return \Drupal\lms_user\Entity\UserCourseInterface|null
   */
  public function getUserCourse(ProductInterface $course, UserInterface $user);

  /**
   * @param $course
   * @param $user_id
   * @return mixed
   */
  public function getUserCoursePostSurvey($course, $user_id);

  /**
   * @param \Drupal\commerce_product\Entity\ProductInterface $course
   * @param \Drupal\user\UserInterface $user
   *
   * @return bool
   */
  public function hasUserCourse(ProductInterface $course, UserInterface $user);

  /**
   * @param \Drupal\lms_user\Entity\UserClassInterface $userClass
   * @param \Drupal\commerce_product\Entity\ProductInterface $course
   * @param \Drupal\user\UserInterface $user
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *
   * @return \Drupal\lms_user\Entity\UserCourseInterface
   */
  public function createUserCourse(UserClassInterface $userClass, ProductInterface $course, UserInterface $user, OrderInterface $order);

  /**
   * @param \Drupal\webform\WebformSubmissionInterface $submission
   */
  public function addClassPreSurvey(WebformSubmissionInterface $submission);

  /**
   * @param \Drupal\webform\WebformSubmissionInterface $submission
   */
  public function addCoursePostSurvey(WebformSubmissionInterface $submission);

  /**
   * @param $host
   * @param $url
   * @return mixed
   */
  public function setUrlWithLanguage($host, $url);

  /**
   * @param $host
   * @param $url
   *
   * @return mixed
   */
  public function setUrlWithDefaultLanguage($host, $url);

  /**
   * @return mixed
   */
  public function getFirstLanguages();

  /**
   * @return mixed
   */
  public function getNations();

  /**
   * @return mixed
   */
  public function getGenderOptions();

  /**
   * @return mixed
   */
  public function getLaguageSiteOptions();

  /**
   * @return mixed
   */
  public function getIsStudentOptions();

  /**
   * @return mixed
   */
  public function reminderPresurvey();

  /**
   * @param array $course_ids
   * @return mixed
   */
  public function addCourseToCart(array $course_ids);

  /**
   * @param $user
   * @return mixed
   */
  public function getUserInformation($user);

  /**
   * @param $pre_survey
   * @return mixed
   */
  public function getPreSurveyData($pre_survey);

  /**
   * @param $post_survey
   * @return mixed
   */
  public function getPostSurveyData($post_survey);

  /**
   * @param string $date
   * @return mixed
   */
  public function getDateData(string $date);

  /**
   * @param string $langcode
   * @param array $date
   * @param string $type
   * @return mixed
   */
  public function getDateFormatResponse(string $langcode, array $date, string $type = 'date_of_event');

  /**
   * @param $user
   * @param $route_name
   * @return mixed
   */
  public function getUrlDashboardWithLanguage($user, $route_name);

  /**
   * @param string $date_value
   * @param bool $is_timestamp
   * @return mixed
   */
  public function getTimeStampWithTimezone(string $date_value, $is_timestamp = TRUE);

  /**
   * @return mixed
   */
  public function getStepOptionByLangcode();

  /**
   * @param $user_course
   * @return mixed
   */
  public function updateAppNumber($user_course);

  /**
   * @param \Drupal\node\NodeInterface $class
   * @return mixed
   */
  public function getNumberOfPreSurveySubmission(NodeInterface $class);

  /**
   * @param \Drupal\node\NodeInterface $class
   * @return mixed
   */
  public function getNumberOfApplicationClass(NodeInterface $class);

  /**
   * @param string $type
   * @return mixed
   */
  public function clearLMSData(string $type = 'all');

}
