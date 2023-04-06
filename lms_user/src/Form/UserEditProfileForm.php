<?php

namespace Drupal\lms_user\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\lms_user\UserManagerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the user_class entity edit forms.
 */
class UserEditProfileForm extends UserPassPolicy {
  /**
   * @var array
   */
  protected $nations;

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * @var \Drupal\lms_user\UserManagerInterface
   */
  protected $lmsUserManager;

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
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param \Drupal\lms_user\UserManagerInterface $lms_user_manager
   */
  public function __construct(
    CountryManagerInterface $country_manager,
    RouteMatchInterface $route_match,
    UserManagerInterface $lms_user_manager
  ) {
    $this->countryManager = $country_manager;
    $this->routeMatch = $route_match;
    $this->lmsUserManager = $lms_user_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('country_manager'),
      $container->get('current_route_match'),
      $container->get('lms_user.manager')
    );
  }

  public function getFormId() {
    return 'user_profile_edit_form';
  }

  public function init() {
    $this->user = User::load(\Drupal::currentUser()->id());
    $this->nations = $this->lmsUserManager->getNations();
    $this->genderOptions = $this->lmsUserManager->getGenderOptions();
    $this->firstLaguageOptions = $this->lmsUserManager->getFirstLanguages();
    $this->laguageSiteOptions = $this->lmsUserManager->getLaguageSiteOptions();
    $this->isStudentOptions = $this->lmsUserManager->getIsStudentOptions();
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->init();
    $form['title'] = [
      '#type' => 'label',
      '#title' => $this->t('Member information'),
      '#attributes' => [
        'class' => 'profile-title',
      ],
    ];
    $session = \Drupal::request()->getSession();
    $is_update = $session->get('submit_success');
    if ($is_update == 1) {
      $session->set('submit_success', 0);
      $form['#message_submit_complete'] = [
        '#value' => $this->t('The edited content was updated successfully.'),
      ];
    }

    $form['wrapper_full_name'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => 'wrapper-full-name',
      ],
    ];

    $form['wrapper_full_name']['label'] = [
      '#type' => 'label',
      '#title' => t('Full name'),
      '#attributes' => [
        'class' => 'label-full-name',
      ],
    ];

    $form['full_name'] = [
      '#type' => 'textfield',
      '#maxlength' => 1000,
      '#default_value' => $this->user->get('field_full_name')->value,
    ];

    $form['pronounce_name'] = [
      '#type' => 'textfield',
      '#maxlength' => 1000,
      '#default_value' => $this->user->get('field_pronounce_name')->value,
    ];
    $form['mail'] = [
      '#type' => 'email',
      '#maxlength' => 1000,
      '#default_value' => $this->user->getEmail(),
    ];
    $form['confirm_mail'] = [
      '#type' => 'email',
      '#maxlength' => 1000,
    ];

    $form['password'] = [
      '#type' => 'password',
      '#description' => '<p class ="txt-anno">' . t('8 to 20 single-byte alphanumeric characters') . '</p><p class ="txt-anno">' . t('It is recommended to set a more complicated password.') . '</p><p class ="txt-anno">' . t('Enter only if you want to change the password.') . '</p>',
      '#maxlength' => 25,
    ];

    $form['password_confirm'] = [
      '#title' => $this->t('Please enter again to confirm.'),
      '#type' => 'password',
      '#maxlength' => 25,
    ];

    $form['field_birthday'] = [
      '#type' => 'date',
      '#default_value' => $this->user->get('field_birthday')->value,
      '#date_date_format' => 'm/d/Y',
    ];
    $form['field_gender'] = [
      '#type' => 'radios',
      '#options' => $this->genderOptions,
      '#default_value' => $this->user->get('field_gender')->value,
    ];

    $form['nation'] = [
      '#type' => 'select',
      '#options' => $this->nations,
      '#default_value' => $this->user->get('field_nationality')->value,
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
      '#default_value' => $this->user->get('field_other_nation')->value,
    ];

    $form['field_first_language'] = [
      '#type' => 'select',
      '#options' => $this->firstLaguageOptions,
      '#default_value' => $this->user->get('field_first_language')->value,
      '#attributes' => [
        'id' => 'first_language_select',
      ],
    ];

    $form['field_other_first_language'] = [
      '#type' => 'textfield',
      '#states' => [
        'visible' => [
          ':input[id="first_language_select"]' => ['value' => '0'],
        ],
      ],
      '#default_value' => $this->user->get('field_other_first_language')->value,
    ];

    $form['field_language_website'] = [
      '#type' => 'radios',
      '#options' => $this->laguageSiteOptions,
      '#default_value' => $this->user->get('field_language_website')->value,
    ];

    $form['field_is_student'] = [
      '#type' => 'radios',
      '#options' => $this->isStudentOptions,
      '#default_value' => $this->user->get('field_is_student')->value,
    ];

    $form['#return'] = Url::fromRoute('lms_user.dashboard')->toString();

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Update'),
      '#attributes' => [
        'class' => ['update-profile__button'],
        'data-twig-suggestion' => 'update_profile_submit',
      ],
    ];

    $form['error'] = [
      '#type' => 'hidden',
    ];
    $form['#theme'] = 'lms_user_edit_profile_templates';
    $form['#attached']['library'][] = 'lms_user/lms_user';
    $form['#password_policy_store'] = TRUE;
    $form['submit']['#submit'][] = [$this, 'submitForm'];
    $form = parent::buildForm($form, $form_state);
    $form['user'] = [
      '#type' => 'value',
      '#value' => $this->user,
    ];
    return $form;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $values = $form_state->getValues();
    $user_ids = \Drupal::entityQuery('user')
      ->condition('mail', $values['mail'])
      ->execute();
    $user_mail_id = reset($user_ids);
    $user = User::load($this->currentUser()->id());
    if (
      $user_mail_id &&
      $user_mail_id != $this->user->id() &&
      $user->mail->value != $values['mail']
    ) {
      $form_state->setErrorByName('mail', t('This e-mail is already taken'));
    }
    if (!empty($values['password'])) {
      /*
       * @TODO: Write validatePassword
      $text_error= $this->lmsUserManager->validatePassword($values['password']);
      if(!empty($text_error)){
      $form_state->setErrorByName('password',$text_error);
      }
       */
    }
    if ($values['nation'] == '0' && !$values['other_nation']) {
      $form_state->setErrorByName(
        'other_nation',
        t('Please input your country/region of origin')
      );
    }
    if (
      $values['field_first_language'] == '0' &&
      !$values['field_other_first_language']
    ) {
      $form_state->setErrorByName(
        'field_other_first_language',
        t('Please input your living country')
      );
    }
    if ($values['mail'] != $values['confirm_mail']) {
      $form_state->setErrorByName('confirm_mail', t('Please input match mail'));
    }
    if (empty($values['full_name'])) {
      $form_state->setErrorByName(
        'full_name',
        t('@name field is required.', ['@name' => t('Full name')])
      );
    }
    if (empty($values['pronounce_name'])) {
      $form_state->setErrorByName(
        'pronounce_name',
        t('@name field is required.', ['@name' => t('Name (Hiragana)')])
      );
    }
    if (empty($values['mail'])) {
      $form_state->setErrorByName(
        'mail',
        t('@name field is required.', ['@name' => t('Mail address')])
      );
    }
    if (empty($values['confirm_mail'])) {
      $form_state->setErrorByName(
        'confirm_mail',
        t('@name field is required.', ['@name' => t('Confirm mail address')])
      );
    }
    if (empty($values['field_birthday'])) {
      $form_state->setErrorByName(
        'field_birthday',
        t('@name field is required.', ['@name' => t('Birthday')])
      );
    }
    if ($values['field_gender'] == NULL) {
      $form_state->setErrorByName(
        'field_gender',
        t('@name field is required.', ['@name' => t('Gender')])
      );
    }
    if ($values['field_language_website'] == NULL) {
      $form_state->setErrorByName(
        'field_language_website',
        t('@name field is required.', ['@name' => t('Language use at website')])
      );
    }
    if ($values['field_is_student'] == NULL) {
      $form_state->setErrorByName(
        'field_is_student',
        t('@name field is required.', [
          '@name' => t('Are you an Toyo University student?'),
        ])
      );
    }
    if ($values['nation'] == '') {
      $form_state->setErrorByName(
        'nation',
        t('@name field is required.', ['@name' => t('Country/region of origin')])
      );
    }
    if ($values['field_first_language'] == '') {
      $form_state->setErrorByName(
        'field_first_language',
        t('@name field is required.', ['@name' => t('First language')])
      );
    }
    if (
      !empty($values['password']) &&
      $values['password'] != $values['password_confirm']
    ) {
      $form_state->setErrorByName(
        'password_confirm',
        t('The specified passwords do not match.')
      );
    }

    $errors = $form_state->getErrors();
    $form['#attached']['drupalSettings']['errors'] = Json::encode($errors);
    $form_state->clearErrors();
    if ($errors) {
      $element = ['error'];
      $form_state->setError(
          $element,
          t('There is an error in the content entered.')
      );

      $form['#attached']['drupalSettings']['error_message'] = t(
        'There is an error in the content entered.'
      );
    }
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering = $form_state->getTriggeringElement();
    if ($triggering['#action_submit'] == 'back') {
      $form_state->setRedirect('lms_user.dashboard');
    }
    else {
      $values = $form_state->getValues();
      $user_after_update = $this->updateUserProfile($this->user, $values);
      if (!$user_after_update) {
        \Drupal::messenger()->addError(t("Error: Can't update user profile"));
      }
      else {
        $session = \Drupal::request()->getSession();
        $session->set('submit_success', 1);
      }
    }
  }

  /**
   * @param $user
   * @param $values
   *
   * @return mixed
   */
  public function updateUserProfile($user, $values) {
    $user->set('field_full_name', $values['full_name']);
    $user->set('field_pronounce_name', $values['pronounce_name']);
    $user->set('mail', $values['mail']);
    if ($values['password']) {
      $user->set('pass', $values['password']);
    }
    $user->set('field_birthday', $values['field_birthday']);
    $user->set('field_gender', $values['field_gender']);
    $user->set('field_nationality', $values['nation']);
    if ($values['nation'] != 0) {
      $user->set('field_other_nation', '');
    }
    else {
      $user->set('field_other_nation', $values['other_nation']);
    }
    $user->set('field_first_language', $values['field_first_language']);
    if ($values['field_first_language'] != 0) {
      $user->set('field_other_first_language', '');
    }
    else {
      $user->set(
        'field_other_first_language',
        $values['field_other_first_language']
          );
    }
    $user->set('field_language_website', $values['field_language_website']);
    $user->set('langcode', $values['field_language_website']);
    $user->set('preferred_langcode', $values['field_language_website']);
    $user->set('field_is_student', $values['field_is_student']);
    $user->save();
    return $user;
  }

}
