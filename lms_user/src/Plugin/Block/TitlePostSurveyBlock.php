<?php

namespace Drupal\lms_user\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Title Post-Survey'.
 *
 * @Block(
 *   id = "title_post_survey_block",
 *   admin_label = @Translation("Title Post-Survey Block"),
 * )
 */
class TitlePostSurveyBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    /** @var \Drupal\lms_commerce\CourseManagerInterface $course_manager */
    $course_manager = \Drupal::service('lms_commerce.course_manager');

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    /** @var \Drupal\commerce_product\Entity\ProductInterface $course */
    $course = \Drupal::request()->get('course');
    if (!empty($course)) {
      $postSurveys = $course->get('field_post_survey')->referencedEntities();
      $class = $course->get('field_class')->referencedEntities();

      if (!empty($postSurveys)) {
        $course = $course->hasTranslation($langcode) ? $course->getTranslation($langcode) : $course;
        if ($class) {
          $class = reset($class);
          $class = $class->hasTranslation($langcode) ? $class->getTranslation($langcode) : $class;
        }

        $end_date = $course_manager->getCourseEndDateFormat($course);
        $end_date_time = $course_manager->getCourseEndDateRangeFormat($course);
        $end_full_date_time = $end_date . ' ' . $end_date_time;
        /** @var \Drupal\node\NodeInterface $professor */
        $professor = $course->field_professor->entity;
        $professor = $professor->hasTranslation($langcode) ? $professor->getTranslation($langcode) : $professor;
        $professor_name = $professor->label();
        $professor_department = $professor->field_professor_department_name->value;

        $title = $this->t('Questionnaire after attendance');
        $markup = "<div class='custom-title-post-survey-block'>$title</div>";
        $markup .= "<div class='custom-post-survey-info'>";
        $markup .= "<div class='custom-course-label'>" . $course->label() . "</div>";
        $markup .= "<div class='custom-course-professor-post-survey-block'><span class='professor-name'>$professor_name</span> / <span class='professor-department'>$professor_department</span></div>";
        $markup .= "<div class='custom-post-survey-date'>$end_full_date_time</div>";
        $markup .= "<div class='custom-class-post-survey-block'>" . $class->label() . "</div>";
        $markup .= "</div>";

        return [
          '#markup' => $markup,
        ];
      }
    }

    return [
      '#markup' => NULL,
    ];

  }

}
