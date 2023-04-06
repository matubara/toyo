<?php

namespace Drupal\lms_user\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\lms_mail\Mail\LmsUserMailInterface;
use Drupal\lms_user\UserManagerInterface;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Form controller for the user_class entity edit forms.
 */
class UserRegisterForm extends UserPassPolicy {

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $currentUser;

  /**
   * @var string
   */
  protected $step;

  /**
   * @var array
   */
  protected $steps_options;

  /**
   * @var string
   */
  protected $current_langcode;

  /**
   * @var integer
   */
  protected $number_step;

  /**
   * @var string
   */
  protected $token_key;

  /**
   * @var array
   */
  protected $nations;

  /**
   * @var object
   */
  protected $tempstore;

  /**
   * @var array
   */
  protected $user_register;

  /**
   * @var string
   */
  protected $register_completed_token;

  /**
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempstor;

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * @var \Drupal\lms_mail\Mail\LmsUserMailInterface
   */
  protected $lmsUserMail;

  /**
   * @var \Drupal\lms_user\UserManagerInterface
   */
  protected $lmsUserManager;

  /**
   * @var array
   */
  protected $langueOptions;

  /**
   * @var array
   */
  protected $genderOptions;

  /**
   * @var array
   */
  protected $firstLaguageOptions;

  /**
   * @var array
   */
  protected $laguageSiteOptions;

  /**
   * @var array
   */
  protected $isStudentOptions;

  /**
   * UserRegisterForm constructor.
   *
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $private_tempstor
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param \Drupal\lms_mail\Mail\LmsUserMailInterface $lms_user_mail
   * @param \Drupal\lms_user\UserManagerInterface $lms_user_manager
   */
  public function __construct(CountryManagerInterface $country_manager, PrivateTempStoreFactory $private_tempstor, RouteMatchInterface $route_match, LmsUserMailInterface $lms_user_mail, UserManagerInterface $lms_user_manager) {
    $this->countryManager = $country_manager;
    $this->privateTempstor = $private_tempstor;
    $this->routeMatch = $route_match;
    $this->lmsUserMail = $lms_user_mail;
    $this->lmsUserManager = $lms_user_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('country_manager'),
      $container->get('tempstore.private'),
      $container->get('current_route_match'),
      $container->get('lms_mail.lms_user'),
      $container->get('lms_user.manager')
    );
  }

  public function getFormId() {
    return 'user_register_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->init();
    switch ($this->step) {
      case 'information':
        if (!$this->currentUser->isAnonymous()) {
          throw new NotFoundHttpException();
        }
        break;

      case 'review':
        if ($this->currentUser->isAnonymous()) {
          if (!$this->user_register) {
            return new RedirectResponse(Url::fromRoute('lms_user.register', ['step' => 'information'])
              ->toString());
          }
        }
        else {
          throw new NotFoundHttpException();
        }
        break;

      case 'send-verification-email':
        if ($this->currentUser->isAnonymous()) {
          if (!$this->user_register) {
            return new RedirectResponse(Url::fromRoute('lms_user.register', ['step' => 'information'])
              ->toString());
          }
          else {
            $this->tempstore->delete('user_register');
          }
        }
        else {
          throw new NotFoundHttpException();
        }
        break;

      case 'completed':
        if ($this->currentUser->isAuthenticated()) {
          if (!$this->register_completed_token) {
            throw new NotFoundHttpException();
          }
          else {
            $this->tempstore->delete($this->token_key);
          }
        }
        else {
          throw new NotFoundHttpException();
        }
        break;

      default:
        throw new NotFoundHttpException();
    }

    $form['#cache']['max-age'] = 0;
    $form['#cache']['contexts'] = ["languages:language_interface"];
    $form['#attributes']['novalidate'] = 'novalidate';

    $form['register_step'] = [
      '#theme' => 'lms_user_register_steps',
      '#steps' => $this->step,
      '#number_step' => $this->number_step,
      '#steps_options' => $this->steps_options,
      '#langcode' => $this->current_langcode,
    ];

    $form['#attached']['library'][] = 'lms_user/lms_user';

    $form['#attached']['drupalSettings']['confirm_pass'] = t('Please enter again to confirm.');
    $form['#steps'] = $this->step;
    if ($this->step == 'information') {
      $form['full_name'] = [
        '#type' => 'textfield',
        '#maxlength' => 1000,
        '#required_error' => t('@name field is required.', ['@name' => t('Full name')]),
      ];

      $form['pronounce_name'] = [
        '#type' => 'textfield',
        '#maxlength' => 1000,
        '#required_error' => t('@name field is required.', ['@name' => t('Name (Hiragana)')]),
      ];

      $form['mail'] = [
        '#type' => 'textfield',
        '#maxlength' => 1000,
        '#required_error' => t('@name field is required.', ['@name' => t('Mail address')]),
      ];
      $form['confirm_mail'] = [
        '#type' => 'textfield',
        '#maxlength' => 1000,
      ];

      $form['password'] = [
        '#type' => 'password',
        '#maxlength' => 25,
        '#required_error' => t('@name field is required.', ['@name' => t('Password')]),
      ];
      $form['confirm_password'] = [
        '#type' => 'password',
        '#maxlength' => 25,
        '#required_error' => t('@name field is required.', ['@name' => t('Password')]),
      ];

      $form = parent::buildForm($form, $form_state);
      $form['field_birthday'] = [
        '#type' => 'date',
        '#max' => '9999-12-31',
        '#validated' => TRUE,
        '#required_error' => t('@name field is required.', ['@name' => t('Birthday')]),
      ];

      $form['field_gender'] = [
        '#type' => 'radios',
        '#options' => $this->genderOptions,
        '#required_error' => t('@name field is required.', ['@name' => t('Gender')]),

      ];

      $form['nation'] = [
        '#type' => 'select',
        '#options' => $this->nations,
        '#attributes' => [
          'id' => 'nation_select',
        ],
      ];

      $form['other_nation'] = [
        '#type' => 'textfield',
        '#maxlength' => 1000,
        '#states' => [
          'visible' => [
            ':input[id="nation_select"]' => ['value' => '0'],
          ],
        ],
      ];

      $form['field_first_language'] = [
        '#type' => 'select',
        '#options' => $this->firstLaguageOptions,
        '#attributes' => [
          'id' => 'first_language_select',
        ],
      ];

      $form['field_other_first_language'] = [
        '#type' => 'textfield',
        '#maxlength' => 1000,
        '#states' => [
          'visible' => [
            ':input[id="first_language_select"]' => ['value' => '0'],
          ],
        ],

      ];

      $form['field_language_website'] = [
        '#type' => 'radios',
        '#options' => $this->laguageSiteOptions,
      ];

      $form['field_is_student'] = [
        '#type' => 'radios',
        '#options' => $this->isStudentOptions,
        '#required_error' => t('@name field is required.', ['@name' => t('Are you a student of Toyo University?')]),
      ];

      $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $node = Node::load(48);
      $node = $node->hasTranslation($langcode)
          ? $node->getTranslation($langcode)
          : $node;
      $link = new FormattableMarkup('<a href="@href" target="_blank" className="mod-link-text is-blank" rel="noopener noreferrer">@text</a>', [
        '@href' => $node->toUrl()->toString(),
        '@text' => t('terms of use'),
      ]);
      $form['agree'] = [
        '#type' => 'checkbox',
        '#title' => t('Agree to the handling of personal information and @terms', ['@terms' => $link]),
        '#return_value' => 1,
        '#default_value' => 0,
      ];

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => t('Proceed to confirmation screen'),
        '#attributes' => [
          'class' => ['user_register_step_1_button', 'button-next-step'],
          'data-twig-suggestion' => 'user_register_step_1_submit',
        ],
      ];

      if ($this->user_register) {
        $form['full_name']['#default_value'] = $this->user_register['full_name'];
        $form['pronounce_name']['#default_value'] = $this->user_register['pronounce_name'];
        $form['mail']['#default_value'] = $this->user_register['mail'];
        $form['confirm_mail']['#default_value'] = $this->user_register['confirm_mail'];
        $form['nation']['#default_value'] = $this->user_register['nation'];
        $form['other_nation']['#default_value'] = $this->user_register['other_nation'];
        $form['field_gender']['#default_value'] = $this->user_register['field_gender'];
        $form['field_birthday']['#default_value'] = $this->user_register['field_birthday'];
        $form['field_first_language']['#default_value'] = $this->user_register['field_first_language'];
        $form['field_other_first_language']['#default_value'] = $this->user_register['field_other_first_language'];
        $form['field_language_website']['#default_value'] = $this->user_register['field_language_website'];
        $form['field_is_student']['#default_value'] = $this->user_register['field_is_student'];
      }
    }
    elseif ($this->step == 'review') {
      $review = $this->user_register;
      $review['nation_name'] = $review['nation'] == "0" ? $review['other_nation'] : $this->nations[$review['nation']];
      $review['field_gender'] = $this->genderOptions[$review['field_gender']];
      $review['field_birthday'] = date('Y/m/d', strtotime($review['field_birthday']));
      $review['field_first_language_name'] = $review['field_first_language'] == "0" ? $review['field_other_first_language'] : $this->firstLaguageOptions[$review['field_first_language']];
      $review['field_language_website'] = $this->laguageSiteOptions[$review['field_language_website']];
      $review['field_is_student'] = $this->isStudentOptions[$review['field_is_student']];
      $form['#review'] = $review;
      $form['return'] = [
        '#type' => 'submit',
        '#value' => t('Return'),
        '#submit' => [[$this, 'submitFormReturn']],
        '#attributes' => [
          'class' => ['button-prev-step'],
          'data-twig-suggestion' => 'user_register_return_submit',
        ],
      ];
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => t('Register as a member with the above contents'),
        '#attributes' => [
          'class' => ['button-next-step'],
          'data-twig-suggestion' => 'user_register_step_2_submit',
        ],
      ];
    }
    elseif ($this->step == 'completed') {
      $completed['member_page'] = Url::fromRoute('<front>')->toString();
      $completed['find_course'] = Url::fromRoute('<front>')->toString();
      $completed['top_page'] = Url::fromRoute('<front>')->toString();
      $form['completed'] = $completed;
    }
    $form['error'] = [
      '#type' => 'hidden',
    ];
    $form['#theme'] = 'lms_user_register_templates';
    return $form;
  }

  public function init() {
    $this->currentUser = \Drupal::currentUser();
    $this->step = $this->routeMatch->getParameter('step');
    $this->steps_options = $this->lmsUserManager->getStepOptionByLangcode();
    $this->number_step = $this->getNumberStep($this->step);
    $this->nations = $this->lmsUserManager->getNations();
    $this->tempstore = $this->privateTempstor->get('lms_user_register');
    $this->user_register = $this->tempstore->get('user_register');
    $this->token_key = 'register_completed_' . $this->currentUser->id();
    $this->register_completed_token = $this->tempstore->get($this->token_key);
    $languageManager = \Drupal::languageManager();
    $this->current_langcode = $languageManager->getCurrentLanguage()->getId();
    $langcodes = $languageManager->getLanguages();
    foreach ($langcodes as $langcode) {
      $this->langueOptions[$langcode->getId()] = $langcode->getName();
    }
    $this->genderOptions = $this->lmsUserManager->getGenderOptions();
    $this->firstLaguageOptions = $this->lmsUserManager->getFirstLanguages();
    $this->laguageSiteOptions = $this->lmsUserManager->getLaguageSiteOptions();
    $this->isStudentOptions = $this->lmsUserManager->getIsStudentOptions();

  }

  /**
   * @param $step
   *
   * @return int
   */
  public function getNumberStep($step) {
    switch ($step) {
      case  'information':
        return 1;

      case 'review':
        return 2;

      case 'send-verification-email':
        return 3;

      case 'completed':
        return 4;

      default:
        return 0;
    }
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if ($this->step == 'information') {
      $values = $form_state->getValues();
      $user_ids = \Drupal::entityQuery('user')
        ->condition('mail', $values['mail'])
        ->execute();
      if ($user_ids) {
        $form_state->setErrorByName('mail', t('This e-mail is already taken'));
      }
      if ($values['nation'] == '0' && !$values['other_nation']) {
        $form_state->setErrorByName('other_nation', t('Please input your country/region of origin'));
      }
      if ($values['mail'] != $values['confirm_mail']) {
        $form_state->setErrorByName('confirm_mail', t('Please input match mail'));
      }
      if ($values['field_first_language'] == '0' && !$values['field_other_first_language']) {
        $form_state->setErrorByName('field_other_first_language', t('Please input your living country'));
      }
      if ($values['agree'] == 0) {
        $form_state->setErrorByName('agree', t('Please agree to the handling of personal information and the terms of use'));
      }
      if (empty($values['full_name'])) {
        $form_state->setErrorByName('full_name', t('@name field is required.', ['@name' => t('Full name')]));
      }
      if (empty($values['pronounce_name'])) {
        $form_state->setErrorByName('pronounce_name', t('@name field is required.', ['@name' => t('Name (Hiragana)')]));
      }
      if (empty($values['mail'])) {
        $form_state->setErrorByName('mail', t('@name field is required.', ['@name' => t('Mail address')]));
      }
      elseif (
        !\Drupal::service('email.validator')->isValid($values['mail'])
      ) {
        $form_state->setErrorByName(
          'mail',
          t("Please input the correct email address.", [
            '@name' => t('Mail address'),
          ])
              );
      }
      if (empty($values['confirm_mail'])) {
        $form_state->setErrorByName('confirm_mail', t('@name field is required.', ['@name' => t('Confirm mail address')]));
      }
      if (empty($values['field_birthday'])) {
        $form_state->setErrorByName('field_birthday', t('@name field is required.', ['@name' => t('Birthday')]));
      }
      if ($values['field_gender'] == NULL) {
        $form_state->setErrorByName('field_gender', t('@name field is required.', ['@name' => t('Gender')]));
      }
      if ($values['field_language_website'] == NULL) {
        $form_state->setErrorByName('field_language_website', t('@name field is required.', ['@name' => t('Language use at website')]));
      }
      if ($values['field_is_student'] == NULL) {
        $form_state->setErrorByName('field_is_student', t('@name field is required.', ['@name' => t('Are you an Toyo University student?')]));
      }
      if ($values['nation'] == "") {
        $form_state->setErrorByName('nation', t('@name field is required.', ['@name' => t('Country/region of origin')]));
      }
      if ($values['field_first_language'] == "") {
        $form_state->setErrorByName('field_first_language', t('@name field is required.', ['@name' => t('First language')]));
      }
      if (empty($values['password'])) {
        $form_state->setErrorByName('password', t('@name field is required.', ['@name' => t('Password')]));
      }
      else {
        /*
        @TODO: Write validatePassword
        $text_error= $this->lmsUserManager->validatePassword($values['password']);
        if(!empty($text_error)){
        $form_state->setErrorByName('password',$text_error);
        }
         */
      }
      if (empty($values['confirm_password'])) {
        $form_state->setErrorByName('confirm_password', t('@name field is required.', ['@name' => t('Confirm password')]));
      }
      if ($values['password'] != $values['confirm_password']) {
        $form_state->setErrorByName(
          'password',
          t('@name is not match.', ['@name' => t('Password')])
        );
        $form_state->setErrorByName(
          'confirm_password',
          t('Password is not match.', ['@name' => t('Confirm password')])
        );
      }

      $errors = $form_state->getErrors();
      $form['#attached']['drupalSettings']['errors'] = Json::encode($errors);
      $form_state->clearErrors();
      if ($errors) {
        $element = ['error'];
        $form_state->setError($element, t('There is an error in the content entered.'));
        $form['#attached']['drupalSettings']['error_message'] = t('There is an error in the content entered.');
      }
    }
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if ($this->step == 'information') {
      $this->tempstore->set('user_register', $values);
      $form_state->setRedirect('lms_user.register', ['step' => 'review']);
    }
    elseif ($this->step == 'review') {
      $new_user = $this->createUser($this->user_register);
      if ($new_user) {
        $result = $this->lmsUserMail->sendUserRegisterVerify($new_user, $new_user->getEmail());
        if ($result) {
          $form_state->setRedirect('lms_user.register', ['step' => 'send-verification-email']);
        }
        else {
          \Drupal::messenger()->addError(t("Error: Can't send mail verify"));
        }
      }
      else {
        \Drupal::messenger()->addError(t("Error: Can't create new users"));
      }
    }
  }

  /**
   * @param $user_register
   *
   * @return \Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createUser($user_register) {
    $user_value = [
      'field_full_name' => $user_register['full_name'],
      'field_pronounce_name' => $user_register['pronounce_name'],
      'mail' => $user_register['mail'],
      'pass' => $user_register['password'],
      'field_birthday' => $user_register['field_birthday'],
      'field_gender' => $user_register['field_gender'],
      'field_nationality' => $user_register['nation'],
      'field_other_nation' => $user_register['other_nation'],
      'field_first_language' => $user_register['field_first_language'],
      'field_other_first_language' => $user_register['field_other_first_language'],
      'field_language_website' => $user_register['field_language_website'],
      'field_is_student' => $user_register['field_is_student'],
      'langcode' => $user_register['field_language_website'],
      'preferred_langcode' => $user_register['field_language_website'],
      'name' => $user_register['mail'],
      'status' => 0,
    ];
    $new_user = User::create($user_value);
    $new_user->addRole('student');
    $new_user->save();
    return $new_user;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitFormReturn(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('lms_user.register', ['step' => 'information']);
  }

}
