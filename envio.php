<?php

require_once 'includes/lib/validation/validation.php';

global $wpdb;
global $CONFIG;

$CONFIG = array(
  /* Mail Options */
    'mail_send_to' => 'fecori@gmail.com',
    'mail_contents' => 'includes/mail-content.php',
    'mail_subject' => 'Enviado desde la web.',

  /* Messages */
    'messages' => array(
        'mail_failed' => 'An unknown error has occured while sending your message',
        'form_error' => '<strong>The following errors were encountered</strong><br><ul><li>%s</li></ul>',
        'form_success' => '<strong>Gracias!</strong><br>Tu mensaje ha sido registrado.',
        'form_fields' => array(
            'name' => array(
                'required' => 'Nombre es requerido.'
            ),
            'email' => array(
                'required' => 'Email es requerido.',
                'email' => 'Email es requerido.'
            ),
            'message' => array(
                'required' => 'Testimonio es requerido.'
            ),
            'foto' => array(
                'required' => 'No se ha adjuntado alguna imagen.'
            ),
            'img' => array(
                'required' => 'Archivo no permitido.',
                'invalid' => 'Archivo no permitido.'
            ),
            'honeypot' => array(
                'invalid' => 'You\'re not a human aren\'t you?'
            )
        )
    )
);

function createFormMessage($formdata)
{
  global $CONFIG;

  ob_start();

  extract($formdata);
  include $CONFIG['mail_contents'];

  return ob_get_clean();
}

function validate_honeypot($array, $field)
{
  if ('' !== $array[$field]) {
    $array->add_error($field, 'invalid');
  }
}

function upload_file($array, $field)
{
  if (strpos($array['img'], 'data:image/jpeg;base64') !== false) {
    if (strlen(base64_decode($array['img'])) > 1038439) { //validamos que el String no pese mas de 1Mb
      $array->add_error($field, 'invalid');
    }
  } else {
    $array->add_error($field, 'invalid');
  }
}

function cleanInput($input)
{
  $search = array(
      '@<script[^>]*?>.*?</script>@si',   // Strip out javascript
      '@<[\/\!]*?[^<>]*?>@si',            // Strip out HTML tags
      '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly
      '@<![\s\S]*?--[ \t\n\r]*>@'         // Strip multi-line comments
  );

  $output = preg_replace($search, '', $input);
  return $output;
}

function sanitize($input)
{
  $output = '';
  if (is_array($input)) {
    foreach ($input as $var => $val) {
      $output[$var] = sanitize($val);
    }
  } else {
    if (get_magic_quotes_gpc()) {
      $input = stripslashes($input);
    }
    $input = cleanInput($input);
    $output = $input;
  }
  return $output;
}

$response = array();
$validator = new Validation(sanitize($_POST['cf']));
$validator
    ->pre_filter('trim')
    ->add_rules('name', 'required')
    ->add_rules('email', 'required', 'email')
    ->add_rules('message', 'required')
    ->add_rules('img', 'required')
    ->add_callbacks('img', 'upload_file')
    ->add_callbacks('honeypot', 'validate_honeypot');

if ($validator->validate()) {
  require_once('includes/lib/swiftmail/swift_required.php');

  $transport = Swift_SmtpTransport::newInstance('smtp.mailgun.org', 25)
      ->setUsername('demoajax@sandbox29c2f3507ed44ed995ca7df96c8f86bf.mailgun.org')
      ->setPassword('demoajax');

  $mailer = Swift_Mailer::newInstance($transport);

  $formdata = $validator->as_array();
  $body = createFormMessage($formdata);

  $message = Swift_Message::newInstance();
  $message
      ->setSubject($CONFIG['mail_subject'])
      ->setFrom($formdata['email'])
      ->setTo($CONFIG['mail_send_to'])
      ->setBody($body, 'text/html');

  if (!$mailer->send($message)) {
    $response['success'] = false;
    $response['message'] = $CONFIG['messages']['mail_failed'];
  } else {
    $response['success'] = true;
    $response['message'] = $CONFIG['messages']['form_success'];

    //aca almacena a la db
    $upload_dir = 'images';
    $upload_path = str_replace('/', DIRECTORY_SEPARATOR, $upload_dir) . DIRECTORY_SEPARATOR;

    list($type, $formdata['img']) = explode(';', $formdata['img']);
    list(, $formdata['img']) = explode(',', $formdata['img']);
    $decoded = $formdata['img'] = base64_decode($formdata['img']);

    $filename = uniqid().'.jpg';
    $hashed_filename = md5($filename . microtime()) . '_' . $filename;
    $image_upload = file_put_contents($upload_path . $hashed_filename, $decoded);

    $file = array();
    $file['tmp_name'] = $upload_path . $hashed_filename;
    $file['name'] = $hashed_filename;
    $file['size'] = filesize($upload_path . $hashed_filename);

    //var_dump($file);

  }
} else {
  $response = array(
      'success' => false,
      'message' => sprintf($CONFIG['messages']['form_error'], implode('</li><li>', $validator->errors($CONFIG['messages']['form_fields'])))
  );
}

echo json_encode($response);

exit();