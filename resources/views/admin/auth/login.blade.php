<!DOCTYPE html>
<html lang="tr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="BilBakalim Admin Panel Giriş">
    <meta name="keywords" content="quiz, admin, bilbakalim, login">
    <meta name="author" content="BilBakalim">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}" type="image/x-icon">
    <title>Admin Giriş | BilBakalim</title>
    
    <!-- Google font-->
    <link href="https://fonts.googleapis.com/css?family=Rubik:400,400i,500,500i,700,700i&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,300i,400,400i,500,500i,700,700i,900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/fontawesome.css') }}">
    <!-- ico-font-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/icofont.css') }}">
    <!-- Themify icon-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/themify.css') }}">
    <!-- Flag icon-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/flag-icon.css') }}">
    <!-- Feather icon-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/feather-icon.css') }}">
    <!-- Bootstrap css-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/bootstrap.css') }}">
    <!-- App css-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/style.css') }}">
    <link id="color" rel="stylesheet" href="{{ asset('assets/css/color-1.css') }}" media="screen">
    <!-- Responsive css-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/responsive.css') }}">
    
    <style>
        .show-hide {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            font-size: 14px;
            user-select: none;
        }
        .show-hide:hover {
            color: #495057;
        }
        .form-input {
            position: relative;
        }
    </style>
</head>
<body>
    <!-- login page start-->
    <div class="container-fluid p-0">
        <div class="row m-0">
            <div class="col-12 p-0">    
                <div class="login-card login-dark">
                    <div>
                        <div>
                            <a class="logo" href="{{ route('welcome') }}">
                                <img class="img-fluid for-light" src="{{ asset('assets/images/logo/logo.png') }}" alt="BilBakalim">
                                <img class="img-fluid for-dark" src="{{ asset('assets/images/logo/logo_dark.png') }}" alt="BilBakalim">
                            </a>
                        </div>
                        <div class="login-main"> 
                            <form class="theme-form" method="POST" action="{{ route('login.post') }}">
                                @csrf
                                <h4>Admin Paneli Giriş</h4>
                                <p>Email ve şifrenizi girerek giriş yapın</p>
                                
                                @if ($errors->any())
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if (session('error'))
                                    <div class="alert alert-danger">
                                        {{ session('error') }}
                                    </div>
                                @endif
                                
                                <div class="form-group">
                                    <label class="col-form-label">Email Adresi</label>
                                    <input class="form-control @error('email') is-invalid @enderror" 
                                           type="email" 
                                           name="email" 
                                           value="{{ old('email') }}" 
                                           required 
                                           placeholder="example@mail.com"
                                           autocomplete="email">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group">
                                    <label class="col-form-label">Şifre</label>
                                    <div class="form-input position-relative">
                                        <input class="form-control @error('password') is-invalid @enderror" 
                                               type="password" 
                                               name="password" 
                                               required 
                                               placeholder="*********"
                                               autocomplete="current-password">
                                        <div class="show-hide"><span class="show"></span></div>
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                
                                <div class="form-group mb-0">
                                    <div class="checkbox p-0">
                                        <input id="checkbox1" type="checkbox" name="remember">
                                        <label class="text-muted" for="checkbox1">Beni Hatırla</label>
                                    </div>
                                    <div class="text-end mt-3">
                                        <button class="btn btn-primary btn-block w-100" type="submit">Giriş Yap</button>
                                    </div>
                                </div>
                                
                              
                                
                                <p class="mt-4 mb-0 text-center">
                                    <a href="{{ route('welcome') }}">← Ana Sayfaya Dön</a>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- login page end-->
    
    <!-- latest jquery-->
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <!-- Bootstrap js-->
    <script src="{{ asset('assets/js/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <!-- feather icon js-->
    <script src="{{ asset('assets/js/icons/feather-icon/feather.min.js') }}"></script>
    <script src="{{ asset('assets/js/icons/feather-icon/feather-icon.js') }}"></script>
    <!-- Theme js-->
    <script src="{{ asset('assets/js/script.js') }}"></script>
    
    <script>
        $(document).ready(function() {
            // Show/hide password
            $('.show-hide').on('click', function(e) {
                e.preventDefault();
                var input = $(this).siblings('input[type="password"], input[type="text"]');
                var type = input.attr('type');
                var showText = $(this).find('.show');
                
                if (type === 'password') {
                    input.attr('type', 'text');
                  
                } else {
                    input.attr('type', 'password');
                 
                }
            });
        });
    </script>
</body>
</html>
