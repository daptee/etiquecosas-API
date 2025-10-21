ALTER TABLE products
    ADD COLUMN is_sale BOOLEAN NOT NULL DEFAULT TRUE;


SET SQL_SAFE_UPDATES = 0;

UPDATE products
SET 
    shipping_text = '<p data-start="94" data-end="243">📦 <strong data-start="97" data-end="136">&iexcl;Este producto est&aacute; listo para vos!</strong><br data-start="136" data-end="139">Lo tenemos en stock y disponible para <strong data-start="177" data-end="197">retiro inmediato</strong> en nuestro local de <strong data-start="218" data-end="240">Villa Crespo, CABA</strong>.</p>
 <p data-start="245" data-end="307">Tambi&eacute;n pod&eacute;s elegir <strong data-start="266" data-end="302">env&iacute;o a domicilio a todo el pa&iacute;s</strong> 🚚</p>
 <p data-start="309" data-end="426">No tiene demoras, solo esper&aacute; la notificaci&oacute;n que te avisa cuando est&aacute; empaquetado&hellip; &iexcl;y ya pod&eacute;s venir a buscarlo! ✨</p>',
    shipping_time_text = '<p data-start="92" data-end="288">🚚 <strong data-start="95" data-end="128">Si elegiste env&iacute;o a domicilio</strong>, una vez que tu pedido est&eacute; listo, el <strong data-start="167" data-end="218">despacho puede demorar entre 1 y 2 d&iacute;as h&aacute;biles</strong> por el proceso de empaque (&iexcl;nos gusta que todo salga perfecto! 😉).</p>
 <p data-start="290" data-end="328">📦 <strong data-start="293" data-end="326">Tiempos estimados de entrega:</strong></p>
 <ul data-start="329" data-end="414">
 <li data-start="329" data-end="369">
 <p data-start="331" data-end="369"><strong data-start="331" data-end="346">CABA y GBA:</strong> entre <strong data-start="353" data-end="367">24 y 72 hs</strong></p>
 </li>
 <li data-start="370" data-end="414">
 <p data-start="372" data-end="414"><strong data-start="372" data-end="391">Resto del pa&iacute;s:</strong> entre <strong data-start="398" data-end="412">72 y 96 hs</strong></p>
 </li>
 </ul>
 <p data-start="416" data-end="514">🕒 Ten&eacute; en cuenta que en temporada de <strong data-start="454" data-end="472">Vuelta al Cole</strong> los tiempos pueden variar un poquito 💛</p>',
    notifications_text = '<p data-start="95" data-end="243">💌 <strong data-start="98" data-end="137">Una vez que tu compra est&eacute; aprobada</strong>, vas a empezar a recibir por mail todas las notificaciones con la informaci&oacute;n y el estado de tu pedido.</p>
 <p data-start="245" data-end="358">📬 <strong data-start="248" data-end="277">&iquest;No te lleg&oacute; ning&uacute;n mail?</strong> Escribinos a <strong data-start="291" data-end="318"><a class="decorated-link cursor-pointer" rel="noopener" data-start="293" data-end="316">info@etiquecosas.com.ar</a></strong> y te ayudamos con lo que necesites 💕</p>
 <p data-start="360" data-end="523">👀 <strong data-start="363" data-end="408">Si ya est&aacute;s recibiendo las notificaciones</strong> y quer&eacute;s seguir el recorrido de tu pedido paso a paso, pod&eacute;s hacerlo directamente desde <strong data-start="497" data-end="510">tu cuenta</strong> en la web.</p>';

SET SQL_SAFE_UPDATES = 1;

UPDATE products
SET shipping_time_text = '<p data-start="89" data-end="270">⏳ <strong data-start="91" data-end="139">Este producto se hace especialmente para vos</strong>, por eso tiene un tiempo de producci&oacute;n estimado de <strong data-start="191" data-end="210">10 d&iacute;as h&aacute;biles</strong>.<br data-start="211" data-end="214">Siempre hacemos lo posible para que te llegue antes 💪</p>
 <p data-start="272" data-end="360">Si eleg&iacute;s env&iacute;o, ten&eacute; en cuenta que <strong data-start="308" data-end="357">el tiempo de entrega se suma al de producci&oacute;n</strong>.</p>
 <p data-start="362" data-end="494">👉 Los tiempos se cuentan <strong data-start="388" data-end="407">en d&iacute;as h&aacute;biles</strong>, a partir de la aprobaci&oacute;n del pago.<br data-start="444" data-end="447">(S&aacute;bados, domingos y feriados no se cuentan 😉)</p>'
WHERE slug IN (
    'combo-escolar-tematicas-13998',
    'combo-etiquetas-super-mini-sello-universal-11194',
    'combo-etiquetas-tematicas-sello-universal-4613',
    'combo-jardin-tematicas-14120',
    'combo-maternal-141',
    'combo-primaria-79',
    'combo-simones-18799',
    'combo-ultra-zombies-19655',
    'etiquetas-cierra-bolsas-5-cm-11010',
    'etiquetas-para-cumpleanos-35-cm-10827',
    'etiquetas-personalizadas-para-dedicar-regalos-13547',
    'souvenir-8-planchas-personalizadas-de-stickers-para-jugar-y-decorar-13530',
    '10mts-para-coser-con-tu-logo-1591',
    '10mts-planchables-con-tu-logo-1594',
    'etiquetas-autoadhesivas-con-tu-marca-medidas-a-eleccion-1595',
    'etiquetas-para-coser-6-x-1-cm-1579',
    'etiquetas-para-planchar-6-x-1-cm-1586',
    'logos-transfer-con-relieve-71134',
    'sello-de-madera-medida-chicamediana-2449',
    'sello-de-madera-medidas-grandes-2488',
    'tags-colgantes-de-carton-1550',
    'tarjetas-personales-frente-y-dorso-1568',
    'etiquetas-para-frascos-personalizadas-vos-elegis-lo-que-dicen-1411',
    'etiquetas-rotuladoras-personalizadas-1396',
    'banda-de-silicona-personalizada-1-unidad-52944',
    'bandas-de-silicona-personalizadas-2-unidades-52796',
    'tag-con-nombre-personalizado-barbie-51957',
    'tag-con-nombre-personalizado-cursiva-51554',
    'tag-con-nombre-personalizado-mayuscula-51480',
    'tag-con-nombre-personalizado-minuscula-51529',
    'tiracierres-con-nombre-personalizados-pack-x-3-51675',
    'identificador-de-mochila-personalizado-80839',
    'botella-personalizada-con-nombre-transfer-71036',
    'nombres-personalizados-para-camisetas-deportivas-91136',
    'porta-chupetes-de-silicona-personalizado-68236',
    'porta-lapiz-personalizado-68168',
    'zapatilla-didactica-de-aprendizaje-personalizada-con-nombre-91639',
    'sello-personalizado-rectangular-481',
    'sello-personalizado-redondo-34528',
    'mochila-estrellas-personalizada-con-nombre-79168'
);
