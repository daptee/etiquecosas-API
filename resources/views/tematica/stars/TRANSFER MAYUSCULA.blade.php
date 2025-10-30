<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <!-- <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Oswald:400,700" media="screen"> -->
  <!-- <link href="https://fonts.googleapis.com/css2?family=Titan+One&display=swap" rel="stylesheet"> -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <style type="text/css">
    @font-face {
      font-family: 'Antonio';
      src: url("{{ public_path('fonts/Antonio.ttf') }}") format('truetype');
      font-weight: normal;
      font-style: normal;
    }

    body {
      /* font-size: 'Helvetica', sans-serif; */
    }

    .hoja {
      padding-left: 5px;
      width: 18.5cm;
      height: 29cm;
    }

    .transfer-container {
      height: 9.3cm;
      width: 14.5cm;
      margin: 0 auto;
    }

    .transfer-info-container {
      width: 100%;
      height: 1.8cm;
      display: inline-block;
      position: relative;
    }

    .transfer-stars-conainer {
      width: 5cm;
      height: 1.8cm;
      vertical-align: middle;
      text-align: center;
      /* display: inline-block; */
      position: relative;
    }

    .star {
      width: 24px;
      height: 24px;
      /* display: inline-block; */
      position: absolute;
      top: 50%;
      transform: rotate(-22.5deg) translateY(-50%);
      left: 50px;
    }

    .star2 {
      left: 78px;
    }

    .star3 {
      left: 106px;
    }

    .star4 {
      left: 134px;
    }

    .star-img {
      width: 24px;
      height: 24px;
    }

    .transfer-nro-pedido {
      /* display: inline-block; */
      /* text-align: center; */
      /* vertical-align: middle; */
      /* font-size: 20px; */
      /* color: #000000; */
      /* transform: rotate(180deg); */
      position: absolute;
      top: 10px;
      /* top: 50%; */
      transform: rotate(180deg);
      left: 180px;
      white-space: nowrap;
      /* width: 6cm; */
    }

    .transfer-name {
      font-size: 16px;
      font-family: 'Helvetica', sans-serif !important;
      color: #000000;
    }

    .nro-pedido {
      font-weight: 700;
      font-size: 16px;
      font-family: 'Helvetica', sans-serif !important;
      color: #000000;
    }

    .logo {
      width: 1.3cm;
      height: 1.3cm;
      display: inline-block;
      /* margin-left: 1cm; */
      transform: translateY(-50%) rotate(-90deg);
      position: absolute;
      top: 50%;
      right: 10px;
    }

    .tranfer-names-container {
      margin: 0 auto;
      width: 12.8cm;
      height: 7cm;
    }

    .transfer-name-container {
      height: 7cm;
      width: 3.2cm;
      margin: 0;
      padding: 0;
      float: left;
      position: relative;
    }

    .name {
      font-family: 'Antonio', sans-serif;
      font-weight: 400;
      font-style: normal;
      font-size: 78px;
      margin: 0;
      position: absolute;
      left: 50%;
      top: 50%;
      transform: translate(-50%, -50%) rotate(-90deg);
      text-transform: uppercase;
      white-space: nowrap;
      line-height: 1;
      /* width: 7cm;
      height: 3.2cm; */
      text-align: center;
      vertical-align: middle;
    }

    @page {
      margin-left: 1.0cm;
      margin-right: 1.5cm;
      margin-top: 0.4cm;
      margin-bottom: 0.2cm;
    }
  </style>
</head>

<body>
  <div class="hoja">
    <div class="transfer-container" style="{{ $plantilla['isWhite'] ? 'background-color: #CACACA;' : '' }}">
      <div class="transfer-info-container">
        <div class="transfer-stars-conainer">
          <div class="star star1"><img class="star-img" src="{{ $plantilla['images'][0] }}" alt=""></div>
          <div class="star star2"><img class="star-img" src="{{ $plantilla['images'][1] }}" alt=""></div>
          <div class="star star3"><img class="star-img" src="{{ $plantilla['images'][2] }}" alt=""></div>
          <div class="star star4"><img class="star-img" src="{{ $plantilla['images'][3] }}" alt=""></div>
        </div>
        <p class="transfer-nro-pedido"><span class="transfer-name">Nombre Transfer - </span><span class="nro-pedido">PEDIDO #{{$product_order->order->id_external}}</span></p>
        <img class="logo" src="{{ public_path('icons/mail/etiquecosas_logo-rosa.png') }}" alt="Logo Etiquecosas">
      </div>
      <div class="tranfer-names-container">
        <div class="transfer-name-container">
          <p class="name" style="color: cmyk({{ $plantilla['colores'][0] }}); font-size: {{ $plantilla['fontSize'] }}">{{ mb_strtoupper($product_order->name) }}</p>
        </div>
        <div class="transfer-name-container">
          <p class="name" style="color: cmyk({{ $plantilla['colores'][1] }}); font-size: {{ $plantilla['fontSize'] }}">{{ mb_strtoupper($product_order->name) }}</p>
        </div>
        <div class="transfer-name-container">
          <p class="name" style="color: cmyk({{ $plantilla['colores'][2] }}); font-size: {{ $plantilla['fontSize'] }}">{{ mb_strtoupper($product_order->name) }}</p>
        </div>
        <div class="transfer-name-container">
          <p class="name" style="color: cmyk({{ $plantilla['colores'][3] }}); font-size: {{ $plantilla['fontSize'] }}">{{ mb_strtoupper($product_order->name) }}</p>
        </div>
      </div>
    </div>
  </div>
</body>

</html>