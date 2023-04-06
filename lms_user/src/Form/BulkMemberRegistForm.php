<?php

namespace Drupal\lms_user\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\lms_user\UserManagerInterface;
use Drupal\user\Entity\User;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BulkMemberRegistForm extends FormBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\lms_user\UserManagerInterface
   */
  protected $userManager;

  protected $langueOptions;

  protected $genderOptions;
  protected $firstLaguageOptions;
  protected $laguageSiteOptions;
  protected $isStudentOptions;
  protected $nations;

  /**
   * BulkMemberRegistForm constructor.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param $user_manager
   * @param $language_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, UserManagerInterface $user_manager, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->userManager = $user_manager;

    $this->current_langcode = $language_manager->getCurrentLanguage()->getId();
    $langcodes = $language_manager->getLanguages();
    foreach ($langcodes as $langcode) {
      $this->langueOptions[$langcode->getId()] = $langcode->getName();
    }

    $this->genderOptions = array_map(function ($item) {
      return $item instanceof TranslatableMarkup ? $item->render() : $item;
    }, $this->userManager->getGenderOptions());

    $this->firstLaguageOptions = array_map(function ($item) {
      return $item instanceof TranslatableMarkup ? $item->render() : $item;
    }, $this->userManager->getFirstLanguages());

    $this->laguageSiteOptions = array_map(function ($item) {
      return $item instanceof TranslatableMarkup ? $item->render() : $item;
    }, $this->userManager->getLaguageSiteOptions());

    $this->isStudentOptions = array_map(function ($item) {
      return $item instanceof TranslatableMarkup ? $item->render() : $item;
    }, $this->userManager->getIsStudentOptions());

    $this->nations = array_map(function ($item) {
      return $item instanceof TranslatableMarkup ? $item->render() : $item;
    }, $this->userManager->getNations());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('lms_user.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bulk_member_regist_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File*'),
      '#size' => 20,
      '#description' => t('CSV format only'),
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#upload_location' => 'public://csv_imports/',
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $csv_file_id = $form_state->getValue('csv_file')[0];
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->entityTypeManager->getStorage('file')->load($csv_file_id);

    $regist_data = $this->getRegistDataFromCSVFile($file);
    if (!$regist_data) {
      $this->messenger()->addError('CSVヘッダーを再度確認してください。');
      return;
    }

    global $_uoffset;
    $_uoffset = 0;
    foreach ($regist_data as $key => $item) {
      if (empty($item['reason'])) {
        $regist_data[$key] = $this->createUser($item);
        $_uoffset++;
      }
    }
    /**@var \Drupal\file\FileInterface $export_file */
    $export_file = $this->exportCSVRegistrationResult($regist_data, $file->label());
    $import_content = $this->createCSVImportContent($file, $export_file);
    $this->messenger()->addMessage($this->t('正しくインポートされました。'));
    $form_state->setRedirect('lms_user.bulk_registration_export');
  }

  /**
   * Get registration data from csv file.
   *
   * @param \Drupal\file\FileInterface $file
   */
  public function getRegistDataFromCSVFile(FileInterface $file) {
    $column_mapping = [
      0 => 'A',
      1 => 'B',
      2 => 'C',
      3 => 'D',
      4 => 'E',
      5 => 'F',
      6 => 'G',
      7 => 'H',
      8 => 'I',
      9 => 'J',
      10 => 'K',
      11 => 'L',
      12 => 'M',
      13 => 'N',
      14 => 'O',
      15 => 'P',
    ];
    $reader = new Csv();
    $inputFileName = \Drupal::service('file_system')->realpath($file->getFileUri());
    $spreadsheet = $reader->load($inputFileName);
    $sheet_data = $spreadsheet->getActiveSheet()->toArray();
    $regist_data = [];

    // Check valid csv header
    $isValid = $this->checkValidCsvHeader($sheet_data);
    if (!$isValid) {
      return $isValid;
    }
    // Get data from csv file
    if (!empty($sheet_data)) {
      for ($i = 1; $i < count($sheet_data); $i++) {
        $field_gender = array_search($sheet_data[$i][6], $this->genderOptions);
        $nation = array_search($sheet_data[$i][7], $this->nations);
        $field_first_language = array_search($sheet_data[$i][9], $this->firstLaguageOptions);
        $field_language_website = array_search($sheet_data[$i][11], $this->laguageSiteOptions);
        $field_is_student = array_search($sheet_data[$i][12], $this->isStudentOptions);

        $regist_data[$i] = [
          'full_name' => $sheet_data[$i][0],
          'pronounce_name' => $sheet_data[$i][1],
          'mail' => $sheet_data[$i][2],
          'password' => $sheet_data[$i][3],
          'field_birthday' => $sheet_data[$i][5],
          'field_gender' => $field_gender,
          'field_gender_old' => $sheet_data[$i][6],
          'nation' => $nation,
          'nation_old' => $sheet_data[$i][7],
          'other_nation' => $sheet_data[$i][8],
          'field_first_language' => $field_first_language,
          'field_first_language_old' => $sheet_data[$i][9],
          'field_language_website' => $field_language_website,
          'field_language_website_old' => $sheet_data[$i][11],
          'field_other_first_language' => $sheet_data[$i][10],
          'field_is_student' => $field_is_student,
          'field_is_student_old' => $sheet_data[$i][12],
          'agree' => $sheet_data[$i][13],
        ];

        $regist_data[$i]['reason'] = '';
        if ($field_gender === FALSE) {
          $regist_data[$i]['import_result'] = 'No';
          $regist_data[$i]['reason'] .= 'カラム' . $column_mapping[6] . ': データを確認してください。' . "\n";
        }
        if ($nation === FALSE) {
          $regist_data[$i]['import_result'] = 'No';
          $regist_data[$i]['reason'] .= 'カラム' . $column_mapping[7] . ': データを確認してください。' . "\n";
        }
        if ($field_first_language === FALSE) {
          $regist_data[$i]['import_result'] = 'No';
          $regist_data[$i]['reason'] .= 'カラム' . $column_mapping[9] . ': データを確認してください。' . "\n";
        }
        if ($field_language_website === FALSE) {
          $regist_data[$i]['import_result'] = 'No';
          $regist_data[$i]['reason'] .= 'カラム' . $column_mapping[10] . ': データを確認してください。' . "\n";
        }
        if ($field_is_student === FALSE) {
          $regist_data[$i]['import_result'] = 'No';
          $regist_data[$i]['reason'] .= 'カラム' . $column_mapping[12] . ': データを確認してください。' . "\n";
        }

        $isExistMail = $this->isExistEmail($regist_data[$i]['mail']);
        if ($isExistMail) {
          $regist_data[$i]['import_result'] = 'No';
          $regist_data[$i]['reason'] .= '既存の学生' . "\n";
        }
        if (!$this->isValidEmail($regist_data[$i]['mail'])) {
          $regist_data[$i]['import_result'] = 'No';
          $regist_data[$i]['reason'] .= $this->t('Please input the correct email address.')->render();
        }
      }
    }
    return $regist_data;
  }

  public function checkValidCsvHeader($sheet_data) {
    $headers = [
      '名前',
      '名前（ひらがな）',
      'メールアドレス',
      'パスワード',
      'パスワード（確認用）',
      '生年月日',
      '性別',
      '国籍',
      'その他国籍',
      '母語',
      'その他母語',
      '言語選択',
      '東洋大学生ですか？',
      '個人情報の取り扱いと利用規約に同意する',
    ];

    // remove white space
    foreach ($sheet_data[0] as $key => $item) {
      $sheet_data[0][$key] = trim($item);
    }
    return $sheet_data[0] == $headers;
  }

  /**
   * @param $user_register
   * @return \Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|User
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createUser($user_register) {
    $user_value = [
      'field_full_name' => $user_register['full_name'],
      'field_pronounce_name' => $user_register['pronounce_name'],
      'mail' => $user_register['mail'],
      'pass' => $user_register['password'],
      'field_birthday' => str_replace('/', '-', $user_register['field_birthday']),
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
      'status' => 1,
    ];
    $new_user = User::create($user_value);
    $new_user->addRole('student');
    $new_user->save();
    $user_register['import_result'] = 'Yes';
    $user_register['reason'] = '';
    return $user_register;
  }

  public function isValidEmail($email) {
    return \Drupal::service('email.validator')->isValid($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
  }

  /**
   * Check existing user by email.
   *
   * @param $email
   *
   * @return bool
   */
  public function isExistEmail($email) {
    $ids = \Drupal::entityQuery('user')->condition('mail', $email)->execute();
    if (!empty($ids)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Create a import content node type.
   *
   * @param \Drupal\file\FileInterface $file
   * @param \Drupal\file\FileInterface $export_file
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createCSVImportContent(FileInterface $file, FileInterface $export_file) {
    $import_content = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'csv_import',
      'title' => $file->label(),
      'field_csv_import_file' => $file->id(),
      'field_csv_export_file' => $export_file->id(),
    ]);
    $import_content->save();
    return $import_content;
  }

  /**
   * Make export csv file.
   *
   * @param array $data
   * @param $data
   * @param $title
   */
  public function exportCSVRegistrationResult(array $data, $title) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Export file');
    $sheet->setCellValue('A1', '名前');
    $sheet->setCellValue('B1', '名前（ひらがな）');
    $sheet->setCellValue('C1', 'メールアドレス');
    $sheet->setCellValue('D1', 'パスワード');
    $sheet->setCellValue('E1', 'パスワード（確認用）');
    $sheet->setCellValue('F1', '生年月日');
    $sheet->setCellValue('G1', '性別');
    $sheet->setCellValue('H1', '国籍');
    $sheet->setCellValue('I1', 'その他国籍');
    $sheet->setCellValue('J1', '母語');
    $sheet->setCellValue('K1', 'その他母語');
    $sheet->setCellValue('L1', '言語選択');
    $sheet->setCellValue('M1', '東洋大学生ですか？');
    $sheet->setCellValue('N1', '個人情報の取り扱いと利用規約に同意する');
    $sheet->setCellValue('O1', 'インポート結果');
    $sheet->setCellValue('P1', '理由');

    $count = 1;
    foreach ($data as $row) {
      $count++;
      $sheet->setCellValue('A' . $count, $row['full_name']);
      $sheet->setCellValue('B' . $count, $row['pronounce_name']);
      $sheet->setCellValue('C' . $count, $row['mail']);
      $sheet->setCellValue('D' . $count, $row['password']);
      // confirm password
      $sheet->setCellValue('E' . $count, $row['password']);
      $sheet->setCellValue('F' . $count, $row['field_birthday']);
      $sheet->setCellValue('G' . $count, $row['field_gender_old']);
      $sheet->setCellValue('H' . $count, $row['nation_old']);
      $sheet->setCellValue('I' . $count, $row['other_nation']);
      $sheet->setCellValue('J' . $count, $row['field_first_language_old']);
      $sheet->setCellValue('K' . $count, $row['field_other_first_language']);
      $sheet->setCellValue('L' . $count, $row['field_language_website_old']);
      $sheet->setCellValue('M' . $count, $row['field_is_student_old']);
      $sheet->setCellValue('N' . $count, $row['agree']);
      $sheet->setCellValue('O' . $count, $row['import_result']);
      $sheet->setCellValue('P' . $count, $row['reason']);
    }

    $writer = new CsvWriter($spreadsheet);

    $path = \Drupal::service('file_system')->realPath('public://csv_imports');
    $file_name = 'result__' . $title;
    $writer->save($path . '/' . $file_name);

    $file_entity = $this->entityTypeManager->getStorage('file')->create([
      'uid' => $this->currentUser()->id(),
      'filename' => $file_name,
      'uri' => 'public://csv_imports/' . $file_name,
      'status' => 1,
    ]);
    $file_entity->save();
    return $file_entity;
  }

}
