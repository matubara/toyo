<?php

namespace Drupal\lms_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\lms_user\Entity\UserClass;
use Drupal\lms_user\Entity\UserCourse;
use Drupal\lms_user\UserClassListBuilder;
use Drupal\lms_user\UserCourseListBuilder;
use Drupal\lms_user\UserManagerInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Drupal\taxonomy\Entity\Term;

class UserClassController extends ControllerBase {

  static $useStaticHeader = FALSE;
  /**
   * @var \Drupal\lms_user\UserManagerInterface
   */
  protected $userManager;

  /**
   * UserClassController constructor.
   */
  public function __construct(UserManagerInterface $user_manager) {
    $this->userManager = $user_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get("lms_user.manager"));
  }

  /**
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function downloadCSV() {
    $request = \Drupal::requestStack()
      ->getCurrentRequest()
      ->query->all();
    $viewId = "content_class_list";
    $displayId = "page_1";
    $exposedInput = [
      "title" => $request["title"] ?? "",
      "type" => $request["type"] ?? "",
      "status" => $request["status"] ?? "",
      "langcode" => $request["langcode"] ?? "",
    ];
    $view = Views::getView($viewId);
    $view->setDisplay($displayId);
    $view->setExposedInput($exposedInput);
    $view->execute();
    $rows = $view->result;
    $file_name = t("class-list.csv");
    $handle = fopen(
      \Drupal::service("file_system")->getTempDirectory() . "/" . "temp",
      "w+b"
    );
    $header = [
      "講座名",
      t("Application numbers"),
      t("Submit numbers"),
      "言語",
      "コンテンツタイプ",
      "投稿者",
      "状態",
      "更新",
    ];
    fputcsv($handle, $header);
    $langcode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();

    foreach ($rows as $row) {
      $row_langcode = $row->node_field_data_langcode;
      $class = $row->_entity;
      $class = $class ? ($class->hasTranslation($row->node_field_data_langcode)
        ? $class->getTranslation($row->node_field_data_langcode)
        : $class) : FALSE;
      $user = $row->_relationship_entities["uid"];
      $name = "";

      if ($user) {
        $name = $user->label();
      }
      $user_classes = $class ? $this->userManager->getUserClassByClass($class, "") : [];
      $number_user_submit = 0;

      foreach ($user_classes as $user_class) {
        $user_courses = $this->userManager->getUserCoursesByUserClass(
          $user_class
        );

        foreach ($user_courses as $user_course) {
          $post_survey = $user_course->get("post_survey")->referencedEntities();

          if (!empty($post_survey)) {
            ++$number_user_submit;
          }
        }
      }
      $user_class = $this->userManager->getUserClassByClass($class, $langcode);

      $data = [
        $class->label(),
        $this->userManager->getNumberOfApplicationClass($class, $row_langcode),
        $this->userManager->getNumberOfPreSurveySubmission(
          $class,
          $row_langcode
        ),
        $class->language()->getName(),
        $class->type->entity->label(),
        $name,
        $class->isPublished() ? t("Published") : t("Unpublished"),
        date("Y/m/d - h:i", $class->get("changed")->getString()),
      ];
      fputcsv($handle, $data);
    }
    rewind($handle);
    $csv_data = stream_get_contents($handle);
    $csv_data = mb_convert_encoding($csv_data, "UTF-8", "UTF-8");
    fclose($handle);
    $response = new Response();
    $response->headers->set("Content-Type", "application/csv");
    $response->headers->set("Charset", "UTF-8");
    // $response->headers->set('content-type:;charset=UTF-8');
    $response->headers->set(
      "Content-Disposition",
      'attachment; filename="' . $file_name . '"'
    );
    $response->setContent($csv_data);

    return $response;
  }

  /**
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function downloadCSVUserClass() {
    $request = \Drupal::requestStack()
      ->getCurrentRequest()
      ->query->all();
    $file_name = t("user-class.csv");
    $uri = "temporary://" . "temp" . $request["batch_id"];
    $headers = [
      "Content-Type" => "application/csv",
      "Content-Disposition" => 'attachment;filename="' . $file_name . '"',
    ];
    return new BinaryFileResponse($uri, 200, $headers, TRUE);
  }

  public function downloadCSVUserCourse() {
    $request = \Drupal::requestStack()
      ->getCurrentRequest()
      ->query->all();
    $file_name = t("user-course.csv");
    $uri = "temporary://temp" . $request["batch_id"];
    $headers = [
      "Content-Type" => "application/csv",
      "Content-Disposition" => 'attachment;filename="' . $file_name . '"',
    ];
    return new BinaryFileResponse($uri, 200, $headers, TRUE);
  }

  public static function dynfields() {
    $fields = [
      "field_application_by_csv" => [
        "title" => "Application by CSV",
        "url" => static function ($form_state) {
          return $form_state->getValue("field_application_by_csv") !== "any"
            ? $form_state->getValue("field_application_by_csv")
            : "any";
        },
        "filter" => static function ($request) {
          $import_type_default =
            $request->query->get("field_application_by_csv") !== ""
              ? $request->query->get("field_application_by_csv")
              : "any";

          return [
            "#type" => "select",
            "#title" => t("Application"),
            "#options" => [
              "any" => "Any",
              "1" => "CSV",
              "0" => "Non-CSV",
            ],
            "#default_value" => $import_type_default,
          ];
        },
        "callback" => static function ($user, $info, $course, $survey, $user_course) {
          return $user_course && $user_course->hasField('field_application_by_csv') && $user_course->get("field_application_by_csv")->getString() === "1"
            ? t("Yes")
            : "";
        },
        "callbackmarkup" => static function ($user, $info, $course, $survey) {
          return [
            "#markup" =>
              $course && $course->hasField('field_application_by_csv') && $course->get("field_application_by_csv")->getString() === "1"
                ? t("Yes")
                : "",
          ];
        },
        "query" => static function ($query, $request, $query_course) {
          $csv_import = $request->query->get("field_application_by_csv");
          if ((string) $csv_import === "1") {
            $query->condition("field_application_by_csv", $csv_import);
          }

          if ((string) $csv_import === "0") {
            $query->notExists("field_application_by_csv");
          }
        },
        "csvpos" => 6,
        "pos" => 12,
        "filterpos" => 100,
      ],
      "category" => [
        "title" => "Category",
        "url" => static function ($form_state) {
          return ($form_state->getValue("category") ?? "") &&
            $form_state->getValue("category") !== "all"
            ? $form_state->getValue("category")
            : "";
        },
        "filter" => static function ($request) {
          $category = $request->query->get("category") ?? "all";

          $langcode = \Drupal::languageManager()
            ->getCurrentLanguage()
            ->getId();
          $term_ids = \Drupal::entityQuery("taxonomy_term")
            ->condition("vid", "category")
            ->execute();

          /** @var \Drupal\taxonomy\TermInterface[] $terms */
          $terms = Term::loadMultiple($term_ids);
          $categories = ["all" => t("All")];
          foreach ($terms as $term) {
            if (!$term) {
              continue;
            }
            $term = $term->hasTranslation($langcode)
              ? $term->getTranslation($langcode)
              : $term;
            $categories[$term->id()] = $term->label();
          }

          return [
            "#type" => "select",
            "#title" => t("Category"),
            "#options" => $categories,
            "#default_value" => $category,
          ];
        },
        "query" => static function ($query, $request, $query_course, $is_class) {
          $category = $request->query->get("category");
          if ($category) {
            if ($is_class) {
              $query_class = $query_course;
              $query_class->condition('field_category', $category);
              return '';
            }
            $query_class = \Drupal::entityQuery("node")
              ->condition("type", "class")
              ->condition("field_category", $category);
            $class_ids = $query_class->execute();
            $class_ids = $class_ids ? $class_ids : [];
            // Query condition 'commerce_product__field_class.field_class_target_id IN ()' cannot be empty
            if (!$class_ids) {
              $query_course->condition("field_class", "-1");
            }
            else {
              $query_course->condition("field_class", $class_ids, "IN");
            }
          }

          return "";
        },
      ],
      "language" => [
        "title" => "Language",
        "query" => static function ($query, $request, $query_course) {
          // Update the query_course
          $language = $request->query->get("language") ?? FALSE;

          if ($language && $language !== "All") {
            $query_course->condition("langcode", $language, "=");
          }

          return "";
        },
        "filter" => static function ($request) {
          $language = $request->query->get("language");

          return [
            "#type" => "select",
            "#multiple" => FALSE,
            "#title" => t("Language"),
            "#default_value" => $language,
            "#options" => [
              "All" => t("- Any -"),
              "ja" => t("Japanese"),
              "en" => t("English"),
              "zh-hans" => t("Chinese"),
              "und" => t("Not specified"),
            ],
          ];
        },
        "url" => static function ($form_state) {
          return $form_state->getValue("language") ?? "";
        },
      ],
      "survey_name" => [
        "title" => "Survey Name",
        "csvpos" => 0,
        "pos" => 0,
        "callback" => static function ($user, $info, $course, $survey, $target_user_c) {
          return $target_user_c->getSurveyName();
        },
      ],
      "item_id" => [
        "title" => "Item id",
        "callback" => static function ($user, $info, $course, $survey, $item) {
          return $item ? $item->id() : "";
        },
      ],
      "survey_submit_id" => [
        "title" => "Survey number",
        "csvpos" => 0,
        "pos" => 0,
        "callback" => static function ($user, $info, $course, $survey) {
          return $survey ? $survey->id() : "";
        },
      ],
      "field_member_id" => [
        "title" => "Membership number",
        "callback" => static function ($user, $info, $course, $survey) {
          return $user
            ? $user->get("field_member_id")->getValue()[0]["value"] ?? "N/A"
            : "N/A";
        },
        "callbackmarkup" => static function ($user, $info, $course, $survey) {
          $v = $user
            ? $user->get("field_member_id")->getValue()[0]["value"] ?? "N/A"
            : "N/A";

          return [
            "#type" => "markup",
            "#markup" => $v,
          ];
        },
        "url" => static function ($form_state) {
          return $form_state->getValue("field_member_id") ?? "";
        },
        "query" => static function ($query, $request) {
          // Update the query
          $field_member_id = $request->query->get("field_member_id") ?? FALSE;

          if ($field_member_id) {
            $query_user = \Drupal::entityQuery("user");
            $group = $query_user
              ->orConditionGroup()
              ->condition("field_member_id", $field_member_id . "%", "LIKE")
              ->condition("field_member_id", $field_member_id, "LIKE")
              ->condition(
                "field_member_id",
                "%" . $field_member_id . "%",
                "LIKE"
              )
              ->condition("field_member_id", "%" . $field_member_id, "LIKE");
            $users = $query_user->condition($group)->execute();

            if (empty($users)) {
              $query->condition("uid", "");
            }
            else {
              $query->condition("uid", $users, "IN");
            }
          }

          return "";
        },
        "filter" => static function ($request) {
          $field_member_id = $request->query->get("field_member_id");

          return [
            "#type" => "textfield",
            "#title" => t("Member ID"),
            "#default_value" => $field_member_id,
            "#attributes" => [
              "size" => 30,
            ],
          ];
        },
        "pos" => 3,
        "filterpos" => 2,
        "csvpos" => 1,
      ],
      "submitted_date" => [
        "title" => "Submitted date",
        "csvpos" => 0,
        "pos" => 8,
        "coursepos" => 10,
        "callback" => static function ($user, $info, $course, $survey) {
          return $survey ? date("Y/m/d", $survey->get("created")->value) : "";
        },
        "callbackmarkup" => static function ($user, $info, $course, $survey) {
          $v = $survey ? date("Y/m/d", $survey->get("created")->value) : "";
          return [
            "#type" => "markup",
            "#markup" => $v,
          ];
        },
      ],
      "registration_date" => [
        "title" => "User registration date",
        "csvpos" => 1,
        "pos" => 5,
        "coursepos" => 6,
        "callback" => static function ($user, $info, $course, $survey) {
          return $user ? date("Y/m/d", $user->getCreatedTime()) : "";
        },
        "callbackmarkup" => static function ($user, $info, $course, $survey) {
          $v = $user ? date("Y/m/d", $user->getCreatedTime()) : "";
          return [
            "#type" => "markup",
            "#markup" => $v,
          ];
        },
      ],
    ];

    return array_reduce(
      array_keys($fields),
      static function ($c, $i) use ($fields) {
        return $c + [$i => $fields[$i] + ["key" => $i]];
      },
      []
    );
  }

  public static $csvFieldsClass = [
    "survey_name",
    "survey_submit_id",
    "submitted_date",
    "registration_date",
    "field_member_id",
  ];

  public static function handleCSVUserClass($request, $offset = FALSE, $limit = FALSE) {
    $thisclass = static::class;
    $newfields = array_map(static function ($i) {
      return UserClassController::dynfields()[$i];
    }, $thisclass::$csvFieldsClass);
    $class = $request->query->get("class");
    $class_start_date = $request->query->get("start");
    $class_end_date = $request->query->get("end");
    $user = $request->query->get("user");
    $email = $request->query->get("email");
    $query = \Drupal::entityQuery("node")->condition("type", "class");

    if ($class) {
      $query->condition("nid", $class);
    }

    if ($class_start_date) {
      $start = date(\DATE_ATOM, strtotime($class_start_date));
      $query->condition("field_period.value", $start, ">=");
    }

    if ($class_end_date) {
      $end = date(\DATE_ATOM, strtotime("+1 day", strtotime($class_end_date)));
      $query->condition("field_period.value", $end, "<=");
    }

    $query_class = $query;

    $query = \Drupal::entityQuery("user_class");

    UserClassController::updateUnique(
      UserClassListBuilder::newfilters(),
      [],
      'query',
      [$query, $request, $query_class, TRUE]
    );

    $node_classes = $query_class->execute();
    if (\count($node_classes) > 0) {
      $query->condition('class', $node_classes, 'IN');
      if ($user) {
        $query_user = \Drupal::entityQuery("user");
        $group = $query_user
          ->orConditionGroup()
          ->condition("field_full_name", $user . "%", "LIKE")
          ->condition("field_full_name", "%" . $user . "%", "LIKE")
          ->condition("field_full_name", "%" . $user, "LIKE")
          ->condition("name", $user, "LIKE")
          ->condition("name", $user . "%", "LIKE")
          ->condition("name", "%" . $user . "%", "LIKE")
          ->condition("name", "%" . $user, "LIKE");
        $users = $query_user->condition($group)->execute();

        if (empty($users)) {
          $query->condition("uid", "");
        }
        else {
          $query->condition("uid", $users, "IN");
        }
      }

      if ($email) {
        $users = \Drupal::entityTypeManager()
          ->getStorage("user")
          ->loadByProperties(["mail" => $email]);

        if ($users) {
          $query->condition("uid", reset($users)->id());
        }
        else {
          $query->condition("uid", NULL);
        }
      }
    }
    elseif (
      \count($node_classes) === 0 &&
      ($class ||
        $class_start_date ||
        $class_end_date ||
        $user ||
        $email)
    ) {
      $query = \Drupal::entityQuery("user_class");
      $query->condition("class", "");
    }

    // Sort by survey name BLANK OR DELETED
    $query = $query->sort('pre_survey.entity:webform_submission.webform_id', 'ASC');
    $query = $query->sort('class.entity:node.field_pre_survey', 'ASC');
    $query = $query->addTag('0|ISNULLORDEL|1|COALESCE~0~class.entity:node.field_pre_survey~1');

    // Sort by survey name BLANK
    $query = $query->sort('pre_survey.entity:webform_submission.webform_id', 'ASC');
    $query = $query->sort('class.entity:node.field_pre_survey', 'ASC');
    $query = $query->addTag('2|ISNULL|3|COALESCE~2~class.entity:node.field_pre_survey~3');

    // Sort by survey name DESC
    $query = $query->sort('pre_survey.entity:webform_submission.webform_id', 'DESC');
    $query = $query->sort('class.entity:node.field_pre_survey', 'ASC');
    $query = $query->addTag('4|WEBFORM|5|COALESCE~4~class.entity:node.field_pre_survey~5');

    // Then submitted date DESC
    $query = $query->sort('pre_survey.entity:webform_submission.created', 'DESC');
    $query = $query->sort('class.entity:node.title', 'ASC');
    $query = $query->sort('user_class_id', 'ASC');
    if ($offset !== FALSE) {
      $query = $query->range($offset, $limit);
    }

    $query = $query->addTag('presurvey');
    return [$query, 'args' => func_get_args()];
  }

  public static function handleCSVUserClass_footers($id, $request, &$context) {
    $ids = [$id];
    $handle = fopen(
      \Drupal::service("file_system")->getTempDirectory() .
        "/" .
        "temp" .
        $context["batch_id"],
      "ab"
    );
    fclose($handle);
  }

  public static function handleCSVUserClass_headers($id, $request, &$context) {
    $thisclass = static::class;
    $newfields = array_map(static function ($i) {
      return UserClassController::dynfields()[$i];
    }, $thisclass::$csvFieldsClass);
    $langcode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
    // $ids = $this->getEntityIds();
    $file_name = t("user-class.csv");
    $handle = fopen(
      \Drupal::service("file_system")->getTempDirectory() .
        "/" .
        "temp" .
        $context["batch_id"],
      "w+b"
    );

    $header = $thisclass::$useStaticHeader ? UserClassController::updateHeader(
      $newfields,
      [
        t("Applied class name"),
        t("Full name"),
        t("Name (Hiragana)"),
        t("Mail address"),
        t("Birthday"),
        t("Gender"),
        t("Country/region of origin"),
        t("First language"),
        t("Language use at website"),
        t("Are you an Toyo University student?"),
      ],
      "csvpos"
    ) : [];
    if ($header) {
      fputcsv($handle, $header);
    }

    fclose($handle);
  }

  public static function handleCSVUserClass_process($id, $request, &$context) {
    $user_class_ids = [$id];
    $langcode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
    $handle = fopen(
      \Drupal::service("file_system")->getTempDirectory() .
        "/" .
        "temp" .
        $context["batch_id"],
      "ab"
    );
    $thisclass = static::class;
    $newfields = array_map(static function ($i) {
      return UserClassController::dynfields()[$i];
    }, $thisclass::$csvFieldsClass);
    $user_classes = UserClass::loadMultiple($user_class_ids);
    $userManager = \Drupal::service("lms_user.manager");

    $b = &batch_get();
    $bdata = &$b['sandbox'];
    foreach ($user_classes as $user_class) {
      $class = $userManager->getClassFromUserClass($user_class);
      $class = $class ? ($class->hasTranslation($langcode)
        ? $class->getTranslation($langcode)
        : $class) : FALSE;
      $user = $user_class->getUser();
      $survey = $pre_survey = $user_class->pre_survey->entity;
      $survey_title = $user_class->getSurveyName();

      // Track for usage later
      $bdata[$survey_title] =
      isset($bdata[$survey_title]) ?
      $bdata[$survey_title] + 1 : 1;
      $user_info = $userManager->getUserInformation($user);
      $survey_data = $userManager->getPreSurveyData($pre_survey);
      $survey_data = $pre_survey ? UserClassController::reOrderSurveyFormElements($pre_survey, $survey_data) : [];

      $data = UserClassController::updateCallback(
        $newfields,
        [$user, $user_info, $class, $pre_survey, $user_class],
        array_merge([
          $class ? $class->label() : '',
          $user_info["full_name"],
          $user_info["pronounce_name"],
          $user_info["mail"],
          $user_info["birthday"],
          $user_info["gender"],
          $user_info["nationality"],
          $user_info["first_language"],
          $user_info["language_website"],
          $user_info["still_student"],
        ], $survey_data),
        "csvpos"
      );

      $thisclass::contextClassHeaders($bdata,
        [$handle, $user, $user_info, $class, $pre_survey, $user_class],
      );

      fputcsv($handle, $data);
    }
    rewind($handle);
    fclose($handle);
  }

  public static $csvFieldsCourse = [
    "survey_name",
    "survey_submit_id",
    "submitted_date",
    "registration_date",
    "field_member_id",
    "field_application_by_csv",
  ];

  public static function handleCSVUserCourse($request, $offset = FALSE, $limit = FALSE) {
    $class = static::class;
    $newfields = array_map(static function ($i) {
      return UserClassController::dynfields()[$i];
    }, $class::$csvFieldsCourse);

    // @refer to getEntityIds of UserCourseListBuilder
    // Same course but bit different
    $class = $request->query->get("class");
    $course = $request->query->get("course");
    $course_start_date = $request->query->get("start");
    $course_end_date = $request->query->get("end");
    $user = $request->query->get("user");
    $mail = $request->query->get("mail");
    $query_course = \Drupal::entityQuery("commerce_product");

    if ($class) {
      $query_course->condition("field_class", $class);
    }

    if ($course) {
      $query_course->condition("product_id", $course);
    }

    if ($course_start_date) {
      $start = date(\DATE_ATOM, strtotime($course_start_date));
      $query_course->condition("field_day_of_the_event.value", $start, ">=");
    }

    if ($course_end_date) {
      $end = date(\DATE_ATOM, strtotime("+1 day", strtotime($course_end_date)));
      $query_course->condition("field_day_of_the_event.value", $end, "<=");
    }

    $query = \Drupal::entityQuery('user_course');
    UserClassController::updateUnique(
      UserCourseListBuilder::newfilters(),
      [],
      'query',
      [$query, $request, $query_course, FALSE]
    );

    $courses = $query_course->execute();
    if (\count($courses) > 0) {
      if ($user) {
        $query_user = \Drupal::entityQuery("user");
        $group = $query_user
          ->orConditionGroup()
          ->condition("field_full_name", $user, "LIKE")
          ->condition("field_full_name", $user . "%", "LIKE")
          ->condition("field_full_name", "%" . $user . "%", "LIKE")
          ->condition("field_full_name", "%" . $user, "LIKE")
          ->condition("name", $user, "LIKE")
          ->condition("name", $user . "%", "LIKE")
          ->condition("name", "%" . $user . "%", "LIKE")
          ->condition("name", "%" . $user, "LIKE");
        $users = $query_user->condition($group)->execute();

        if (empty($users)) {
          $query->condition("uid", "");
        }
        else {
          $query->condition("uid", $users, "IN");
        }
      }

      if ($mail) {
        $users = \Drupal::entityTypeManager()
          ->getStorage("user")
          ->loadByProperties(["mail" => $mail]);

        if ($users) {
          $query->condition("uid", reset($users)->id());
        }
        else {
          $query->condition("uid", NULL);
        }
      }
      $query->condition("course", $courses, "IN");
    }
    elseif (
      \count($courses) === 0 &&
      ($mail || $user || $course || $course_start_date || $course_end_date)
    ) {
      $query->condition("course", "");
    }

    // Sort by survey name BLANK OR DELETED
    $query = $query->sort('post_survey.entity:webform_submission.webform_id', 'ASC');
    $query = $query->sort('course.entity:commerce_product.field_post_survey', 'ASC');
    $query = $query->addTag('0|ISNULLORDEL|1|COALESCE~0~course.entity:commerce_product.field_post_survey~1');

    // Sort by survey name BLANK
    $query = $query->sort('post_survey.entity:webform_submission.webform_id', 'ASC');
    $query = $query->sort('course.entity:commerce_product.field_post_survey', 'ASC');
    $query = $query->addTag('2|ISNULL|3|COALESCE~2~course.entity:commerce_product.field_post_survey~3');

    // Sort by survey name DESC
    $query = $query->sort('post_survey.entity:webform_submission.webform_id', 'DESC');
    $query = $query->sort('course.entity:commerce_product.field_post_survey', 'ASC');
    $query = $query->addTag('4|WEBFORM|5|COALESCE~4~course.entity:commerce_product.field_post_survey~5');

    // Then submitted date DESC
    $query = $query->sort('post_survey.entity:webform_submission.created', 'DESC');
    $query = $query->sort('course.entity:commerce_product.title', 'ASC');
    $query = $query->groupBy('post_survey.entity:webform_submission.id');
    if ($offset !== FALSE) {
      $query = $query->range($offset, $limit);
    }
    $query = $query->addTag('postsurvey');
    return [$query, 'args' => func_get_args()];
  }

  public static function handleCSVUserCourse_footers($id, $request, &$context) {
    $ids = [$id];
    $handle = fopen(
      \Drupal::service("file_system")->getTempDirectory() .
        "/" .
        "temp" .
        $context["batch_id"],
      "ab"
    );
    $class = static::class;
    $newfields = array_map(static function ($i) {
      return UserClassController::dynfields()[$i];
    }, $class::$csvFieldsCourse);
    fclose($handle);
  }

  public static function handleCSVUserCourse_headers($id, $request, &$context) {
    $thisclass = $class = static::class;
    $newfields = array_map(static function ($i) {
      return UserClassController::dynfields()[$i];
    }, $class::$csvFieldsCourse);
    $langcode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
    $file_name = t("user-course.csv");
    $handle = fopen(
      \Drupal::service("file_system")->getTempDirectory() .
        "/" .
        "temp" .
        $context["batch_id"],
      "w+b"
    );

    $header = $thisclass::$useStaticHeader ? UserClassController::updateHeader(
      $newfields,
      [
        t("Applied course name"),
        t("Full name"),
        t("Name (Hiragana)"),
        t("Mail address"),
        t("Birthday"),
        t("Gender"),
        t("Country/region of origin"),
        t("First language"),
        t("Language use at website"),
        t("Are you an Toyo University student?"),
      ],
      "csvpos"
    ) : [];

    if ($header) {
      fputcsv($handle, $header);
    }

    fclose($handle);
  }

  public static function handleCSVUserCourse_process($id, $request, &$context) {
    $ids = [$id];
    $langcode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
    $handle = fopen(
      \Drupal::service("file_system")->getTempDirectory() .
        "/" .
        "temp" .
        $context["batch_id"],
      "ab"
    );
    $class = static::class;
    $newfields = array_map(static function ($i) {
      return UserClassController::dynfields()[$i];
    }, $class::$csvFieldsCourse);
    // $ids = $this->getEntityIds();
    $user_courses = UserCourse::loadMultiple($ids);

    $userManager = \Drupal::service("lms_user.manager");

    $b = &batch_get();
    $bdata = &$b['sandbox'];
    foreach ($user_courses as $user_course) {
      $course = $userManager->getCourseFromUserCourse($user_course);
      $course = $course ? ($course->hasTranslation($langcode)
        ? $course->getTranslation($langcode)
        : $course) : FALSE;
      $user = $user_course->getUser();
      $survey = $post_survey = $user_course->post_survey->entity;
      $survey_title = $user_course->getSurveyName();

      // Track for usage later
      $bdata[$survey_title] =
      isset($bdata[$survey_title]) ?
      $bdata[$survey_title] + 1 : 1;

      $user_info = $userManager->getUserInformation($user);
      $survey_data = $userManager->getPostSurveyData($post_survey);
      $survey_data = $post_survey ? UserClassController::reOrderSurveyFormElements($post_survey, $survey_data) : [];

      $data = UserClassController::updateCallback(
        $newfields,
        [$user, $user_info, $course, $post_survey, $user_course],
        array_merge([
          $course ? $course->getTitle() : '',
          $user_info["full_name"],
          $user_info["pronounce_name"],
          $user_info["mail"],
          $user_info["birthday"],
          $user_info["gender"],
          $user_info["nationality"],
          $user_info["first_language"],
          $user_info["language_website"],
          $user_info["still_student"],
        ], $survey_data),
        "csvpos"
      );

      $class::contextCourseHeaders($bdata,
        [$handle, $user, $user_info, $course, $post_survey, $user_course],
      );

      fputcsv($handle, $data);
    }
    rewind($handle);
    fclose($handle);
  }

  public static function contextCourseHeaders($sandbox, $data) {
    $thisclass = $class = static::class;
    $newfields = array_map(static function ($i) {
      return UserClassController::dynfields()[$i];
    }, $class::$csvFieldsCourse);
    $header = [
        t("Applied course name"),
        t("Full name"),
        t("Name (Hiragana)"),
        t("Mail address"),
        t("Birthday"),
        t("Gender"),
        t("Country/region of origin"),
        t("First language"),
        t("Language use at website"),
        t("Are you an Toyo University student?"),
    ];
    return $thisclass::contextHeaders($sandbox, $data, $header, $newfields);
  }

  public static function contextClassHeaders($sandbox, $data) {
    $thisclass = static::class;
    $newfields = array_map(static function ($i) {
      return UserClassController::dynfields()[$i];
    }, $thisclass::$csvFieldsClass);
    $header = [
      t('Applied class name'),
      t('Full name'),
      t('Name (Hiragana)'),
      t('Mail address'),
      t('Birthday'),
      t('Gender'),
      t('Country/region of origin'),
      t('First language'),
      t('Language use at website'),
      t('Are you an Toyo University student?'),
    ];
    return $thisclass::contextHeaders($sandbox, $data, $header, $newfields);
  }

  public static function contextHeaders($sandbox, $data, $staticHeaders, $dynHeaders) {
    $thisclass = static::class;
    list ($handle, $user, $user_info, $course, $survey, $target_user_c) = $data;
    $survey_title = $target_user_c->getSurveyName();
    if (($sandbox[$survey_title] ?? -1) === 1) {
      $header = UserClassController::getSurveyInputElemets($target_user_c->getSurveyWebform());
      $header = !($thisclass::$useStaticHeader) ?
        UserClassController::updateHeader($dynHeaders, array_merge($staticHeaders, $header), "csvpos"
      ) :
        [];
      // Convert survey_name to survey_title
      if ($header['survey_name'] ?? FALSE) {
        $header['survey_name'] = $survey_title ? $survey_title : 'N/A';
      }

      if ($header) {
        fputcsv($handle, $header);
      }
    }
  }

  public static function updateCallback(
    $newfields,
    $args,
    $existed,
    $pos = "pos"
  ) {
    return self::updateUnique($newfields, $existed, "callback", $args, $pos);
  }

  public static function updateCallbackMarkup(
    $newfields,
    $args,
    $existed,
    $pos = "pos"
  ) {
    return self::updateUnique(
      $newfields,
      $existed,
      "callbackmarkup",
      $args,
      $pos
    );
  }

  public static function updateFilters($newfields, $args, $existed) {
    return self::updateUnique(
      $newfields,
      $existed,
      "filter",
      $args,
      "filterpos"
    );
  }

  public static function updateHeader($newfields, $existed, $pos = "pos") {
    return self::updateUnique($newfields, $existed, "title", [], $pos);
  }

  public static function updateUnique(
    $newfields,
    $existed,
    $key,
    $args = [],
    $orderk = "pos"
  ) {
    // Add keys
    $offset = 0;
    $poses = [];
    $newfields2 = array_reduce(
      array_keys($newfields),
      static function ($c,
 $i) use (
        $args,
        $key,
        $newfields,
        &$offset,
        &$poses,
        $orderk
      ) {
        $it = $newfields[$i];
        $v = FALSE;
        $k = $it["key"] ?? "";

        if (\is_callable($it[$key])) {
          $v = \call_user_func_array($it[$key], $args);
        }
        else {
          $v = t($it[$key]);
        }
        $fallbackpos = $it["pos"] ?? FALSE;
        $pos = $it[$orderk] ?? $fallbackpos;

        if ($pos === FALSE) {
          $pos = $offset++;
        }
        $poses[$k] = (int) $pos;

        return $c + [
          $k => $v,
        ];
      },
      []
    );

    // Put to correct pos
    // Ensure # keys of existed to be out off this order by separated it
    $chars = array_filter(
      $existed,
      static function ($k) {
        return $k[0] === "#";
      },
      \ARRAY_FILTER_USE_KEY
    );
    $existed = array_filter(
      $existed,
      static function ($k) {
        return $k[0] !== "#";
      },
      \ARRAY_FILTER_USE_KEY
    );
    $header = $existed;
    $new = [];
    $rawposes = $poses;
    $total = \count($newfields2) + \count($existed);

    for ($i = 0; $i < $total; ++$i) {
      $m = 10000;

      if (\count($poses)) {
        $m = min($poses);
      }

      // Fallback if empty existed
      if ($i >= $m || !\count($existed)) {
        // Cleanup poses
        $p = array_search($m, $poses, 1);

        if ($p === FALSE) {
          reset($poses);
          $p = key($poses);
        }

        unset($poses[$p]);
        $new[$p] = $newfields2[$p];
      }
      else {
        reset($existed);
        $k = key($existed);
        $new[$k] = current($existed);
        unset($existed[$k]);
      }
    }

    return $chars + $new;
  }

  /**
   * Get input elements of survey form.
   */
  public static function getSurveyInputElemets($webform) {
    if (!empty($webform)) {
      $form_group_elements = $webform->getElementsDecoded()['input'];
      $elements = $webform->getElementsInitializedFlattenedAndHasValue();
      $element_list = [];

      foreach ($form_group_elements as $form_group_key => $form_group_child) {
        if (is_array($form_group_child)) {
          // Check for 1st-level child elements
          if (!empty($elements[$form_group_key])) {
            $element_list[$form_group_key] = $elements[$form_group_key]['#title'];
          }

          // Check for 2nd-level child elements
          foreach ($form_group_child as $child_key => $child_element) {
            if (!empty($elements[$child_key])) {
              $element_list[$child_key] = $elements[$child_key]['#title'];
            }
          }
        }
      }

      return $element_list;
    }
    return [];
  }

  /**
   * Reorder elements based on webform form build setup.
   */
  public static function reOrderSurveyFormElements($webform, $elements) {
    $form_group_elements = $webform->getWebform()->getElementsDecoded()['input'];
    $element_list = [];

    foreach ($form_group_elements as $form_group_key => $form_group_child) {
      if (is_array($form_group_child)) {
        // Check for 1st-level child elements
        if (isset($elements[$form_group_key])) {
          $element_list[$form_group_key] = $elements[$form_group_key];
        }

        // Check for 2nd-level child elements
        foreach ($form_group_child as $child_key => $child_element) {
          if (isset($elements[$child_key])) {
            $element_list[$child_key] = $elements[$child_key];
          }
        }
      }
    }
    return $element_list;
  }

}
