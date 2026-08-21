<title>Madrasa Alfarooqia - <?php echo $translations[$lang]['title']; ?></title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CSS & JS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
  
  <!-- Datepicker CSS & JS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
  
  <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu&display=swap" rel="stylesheet">


  <link rel="stylesheet" href="css/mystyle.css" />
 
  <!-- RTL support for Urdu -->
  <style>
    <?php if ($lang == 'ur'): ?>
    body, .form-control, .btn, .alert, .navbar-nav {
      text-align: right;
      direction: rtl;
    }
    .dropdown-menu {
      text-align: right;
      left: auto;
      right: 0;
    }
    .table th, .table td {
      text-align: right;
    }
    <?php else: ?>
    .table th, .table td {
      text-align: left;
    }
    <?php endif; ?>
  </style>