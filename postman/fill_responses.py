import json,os
postman_dir="c:/project/etiquecosas-API/postman"
def make_auth_err():
    return {"name":"401 - No autenticado","status":"Unauthorized","code":401,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Unauthenticated."},indent=2)}
def make_not_found(resource="Recurso"):
    return {"name":"404 - No encontrado","status":"Not Found","code":404,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":f"{resource} no encontrado"},ensure_ascii=False,indent=2)}
def make_validation_err(fields=None):
    errors=fields or {"campo":["El campo es obligatorio"]}
    return {"name":"422 - Error de validacion","status":"Unprocessable Entity","code":422,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Error de validacion","errors":errors},ensure_ascii=False,indent=2)}
# SALES
sales_path=os.path.join(postman_dir,"Sales.postman_collection.json")
with open(sales_path,encoding="utf-8") as f: sales=json.load(f)
def fill_sales(item):
    name=item.get("name","")
    method=item.get("request",{}).get("method","GET")
    if item.get("response")!=[]: return
    if "dashboard-stats" in name:
        item["response"]=[{"name":"200 - Estadisticas del dashboard","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Estadisticas obtenidas","data":{"total_sales":52,"sales_by_status":[{"sale_status_id":1,"name":"Aprobado","count":10},{"sale_status_id":2,"name":"En produccion","count":15},{"sale_status_id":3,"name":"Listo","count":8},{"sale_status_id":4,"name":"Entregado","count":12},{"sale_status_id":5,"name":"Cancelado","count":3},{"sale_status_id":8,"name":"Pendiente de pago","count":4}],"sales_by_channel":[{"channel_id":1,"name":"Web","count":30},{"channel_id":2,"name":"Instagram","count":10},{"channel_id":3,"name":"WhatsApp","count":8},{"channel_id":4,"name":"Mayorista","count":4}],"revenue_this_month":245000.00,"pending_payments":4}},ensure_ascii=False,indent=2)},make_auth_err()]
    elif "export/wholesale" in name or "export-wholesale" in name:
        item["response"]=[{"name":"200 - Excel mayorista exportado","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"},{"key":"Content-Disposition","value":"attachment; filename=pedidos_mayoristas_2026-05.xlsx"}],"body":"(Excel binario)"},make_auth_err()]
    elif "change-status-admin" in name:
        item["response"]=[{"name":"200 - Estado actualizado","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Estado actualizado","data":{"id":142,"sale_status_id":1,"status":{"id":1,"name":"Aprobado"},"updated_at":"2026-05-26T10:00:00.000000Z"}},ensure_ascii=False,indent=2)},{"name":"422 - Stock insuficiente al aprobar","status":"Unprocessable Entity","code":422,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"No se pudo aprobar el pedido. Stock insuficiente.","errors":[{"product_id":15,"product_name":"Etiquetas personalizadas para colegios","product_variant_id":None,"requested":3,"available":0}]},ensure_ascii=False,indent=2)},make_not_found("Pedido"),make_auth_err()]
    elif "assign-user-sale-multiple" in name:
        item["response"]=[{"name":"200 - Usuario asignado a multiples pedidos","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Usuario asignado a 3 pedidos","data":{"user_id":2,"sale_ids":[140,141,142],"processed":3}},ensure_ascii=False,indent=2)},make_validation_err({"user_id":["El campo user_id es obligatorio"],"sale_ids":["El campo sale_ids es obligatorio"]}),make_auth_err()]
    elif "assign-user" in name and "multiple" not in name and "sale-multiple" not in name:
        item["response"]=[{"name":"200 - Usuario asignado","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Usuario asignado","data":{"id":142,"user_id":2,"user":{"id":2,"name":"Maria","lastName":"Lopez"},"updated_at":"2026-05-26T09:15:00.000000Z"}},ensure_ascii=False,indent=2)},make_validation_err({"user_id":["El campo user_id es obligatorio"]}),make_not_found("Pedido"),make_auth_err()]
    elif "assign-cadete-multiple" in name:
        item["response"]=[{"name":"200 - Cadete asignado a multiples pedidos","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Cadete asignado a 3 pedidos","data":{"cadete_id":5,"sale_ids":[140,141,142],"processed":3}},ensure_ascii=False,indent=2)},make_validation_err({"cadete_id":["El campo cadete_id es obligatorio"],"sale_ids":["El campo sale_ids es obligatorio"]}),make_auth_err()]
    elif "assign-cadete" in name and "multiple" not in name:
        item["response"]=[{"name":"200 - Cadete asignado","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Cadete asignado","data":{"id":142,"cadete_id":5,"cadete":{"id":5,"name":"Diego","lastName":"Ramirez"},"updated_at":"2026-05-26T09:20:00.000000Z"}},ensure_ascii=False,indent=2)},make_validation_err({"cadete_id":["El campo cadete_id es obligatorio"]}),make_not_found("Pedido"),make_auth_err()]
    elif "receiver-data" in name:
        item["response"]=[{"name":"200 - Datos del receptor actualizados","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Datos del receptor actualizados","data":{"id":142,"receiver_name":"Maria Lopez","receiver_dni":"30123456","updated_at":"2026-05-26T10:30:00.000000Z"}},ensure_ascii=False,indent=2)},make_validation_err({"receiver_name":["El campo nombre del receptor es obligatorio"],"receiver_dni":["El campo DNI del receptor es obligatorio"]}),make_not_found("Pedido"),make_auth_err()]
    elif "internal_comments" in name:
        item["response"]=[{"name":"200 - Comentario interno actualizado","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Comentario interno actualizado","data":{"id":142,"internal_comments":"Revisar direccion de entrega","updated_at":"2026-05-26T10:45:00.000000Z"}},ensure_ascii=False,indent=2)},make_not_found("Pedido"),make_auth_err()]
    elif "customer_notes" in name:
        item["response"]=[{"name":"200 - Notas del cliente actualizadas","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Notas del cliente actualizadas","data":{"id":142,"customer_notes":"Sin mani por favor","updated_at":"2026-05-26T10:50:00.000000Z"}},ensure_ascii=False,indent=2)},make_not_found("Pedido"),make_auth_err()]
    elif "client-data" in name:
        item["response"]=[{"name":"200 - Datos del cliente actualizados","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Datos del cliente actualizados","data":{"id":142,"client":{"name":"Maria","email":"maria@mail.com","phone":"1122334455"},"updated_at":"2026-05-26T11:00:00.000000Z"}},ensure_ascii=False,indent=2)},make_validation_err({"client_email":["El campo email debe ser una direccion de correo valida"]}),make_not_found("Pedido"),make_auth_err()]
    elif "update-client" in name:
        item["response"]=[{"name":"200 - Cliente actualizado en el pedido","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Cliente actualizado","data":{"id":142,"client_id":12,"client":{"id":12,"name":"Luciana","lastName":"Martinez","email":"luciana@gmail.com"},"updated_at":"2026-05-26T11:10:00.000000Z"}},ensure_ascii=False,indent=2)},make_validation_err({"client_id":["El campo client_id es obligatorio"]}),make_not_found("Cliente"),make_auth_err()]
    elif "remove-association" in name:
        item["response"]=[{"name":"200 - Asociacion eliminada","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Asociacion eliminada","data":{"id":142,"associated_sale_id":None,"updated_at":"2026-05-26T11:25:00.000000Z"}},ensure_ascii=False,indent=2)},make_not_found("Pedido"),make_auth_err()]
    elif "associate" in name and "remove" not in name:
        item["response"]=[{"name":"200 - Pedidos asociados","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Pedidos asociados","data":{"id":142,"associated_sale_id":140,"updated_at":"2026-05-26T11:20:00.000000Z"}},ensure_ascii=False,indent=2)},make_validation_err({"associated_sale_id":["El campo associated_sale_id es obligatorio"]}),make_not_found("Pedido"),make_auth_err()]
    elif "sales/local" in name and method=="POST":
        item["response"]=[{"name":"200 - Pedido local creado","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Pedido creado","data":{"id":143,"client_id":12,"channel_id":3,"sale_status_id":1,"subtotal":3000.00,"total":3000.00,"status":{"id":1,"name":"Aprobado"},"created_at":"2026-05-26T11:30:00.000000Z"}},ensure_ascii=False,indent=2)},{"name":"422 - Stock insuficiente","status":"Unprocessable Entity","code":422,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Stock insuficiente para completar el pedido","errors":[{"product_id":15,"requested":2,"available":0}]},ensure_ascii=False,indent=2)},make_validation_err({"client_id":["El campo client_id es obligatorio"],"channel_id":["El campo channel_id es obligatorio"],"products":["El campo products es obligatorio"]}),make_auth_err()]
    elif "local/" in name and method=="PUT":
        item["response"]=[{"name":"200 - Pedido local actualizado","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Pedido actualizado","data":{"id":142,"subtotal":4500.00,"total":4500.00,"updated_at":"2026-05-26T11:35:00.000000Z"}},ensure_ascii=False,indent=2)},{"name":"422 - Stock insuficiente","status":"Unprocessable Entity","code":422,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Stock insuficiente para completar el pedido","errors":[{"product_id":15,"requested":3,"available":1}]},ensure_ascii=False,indent=2)},make_not_found("Pedido"),make_auth_err()]
    elif "generate-bulk-pdfs" in name:
        item["response"]=[{"name":"200 - PDFs generados","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"PDFs generados correctamente","data":{"processed":3,"sale_ids":[140,141,142]}},ensure_ascii=False,indent=2)},make_validation_err({"sale_ids":["El campo sale_ids es obligatorio"]}),make_auth_err()]
    elif "generate-pdf" in name:
        item["response"]=[{"name":"200 - PDF generado","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/pdf"},{"key":"Content-Disposition","value":"attachment; filename=pedido-142.pdf"}],"body":"(PDF binario)"},make_not_found("Pedido"),make_auth_err()]
def walk(items):
    for it in items:
        if "item" in it: walk(it["item"])
        else: fill_sales(it)
walk(sales["item"])
with open(sales_path,"w",encoding="utf-8") as f:
    json.dump(sales,f,ensure_ascii=False,indent="	")
print("Sales done")
# PRODUCTS
products_path=os.path.join(postman_dir,"Products.postman_collection.json")
with open(products_path,encoding="utf-8") as f: products=json.load(f)
def fill_products(item):
    name=item.get("name","")
    method=item.get("request",{}).get("method","GET")
    if item.get("response")!=[]: return
    if "bulk-assign-categories" in name:
        item["response"]=[{"name":"200 - Categorias asignadas","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Categorias asignadas a 3 productos","data":{"product_ids":[10,11,12],"category_ids":[2,3],"processed":3}},ensure_ascii=False,indent=2)},make_validation_err({"product_ids":["El campo product_ids es obligatorio"],"category_ids":["El campo category_ids es obligatorio"]}),make_auth_err()]
    elif "apply-text-template" in name:
        item["response"]=[{"name":"200 - Plantilla aplicada","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Plantilla aplicada a 2 productos","data":{"product_ids":[10,11],"template_id":1,"processed":2}},ensure_ascii=False,indent=2)},make_validation_err({"product_ids":["El campo product_ids es obligatorio"],"template_id":["El campo template_id es obligatorio"]}),make_auth_err()]
    elif "POST products/{id}" in name or (method=="POST" and "products/15" in str(item.get("request",{}).get("url",{}))):
        item["response"]=[{"name":"200 - Producto actualizado","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Producto actualizado","data":{"id":15,"name":"Etiquetas personalizadas","updated_at":"2026-05-26T10:00:00.000000Z"}},ensure_ascii=False,indent=2)},make_validation_err({"name":["El campo nombre es obligatorio"]}),make_not_found("Producto"),make_auth_err()]
    elif "exclusions" in name and method=="GET":
        item["response"]=[{"name":"200 - Exclusiones del producto","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Exclusiones obtenidas","data":[{"id":3,"name":"Distribuidora Sur","email":"distrsur@mail.com","client_type_id":2},{"id":7,"name":"Mayorista Norte","email":"norte@mail.com","client_type_id":2}]},ensure_ascii=False,indent=2)},make_not_found("Producto"),make_auth_err()]
    elif "exclusions" in name and method=="POST":
        item["response"]=[{"name":"200 - Exclusiones agregadas","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Exclusiones agregadas","data":{"product_id":15,"excluded_client_ids":[3,7]}},ensure_ascii=False,indent=2)},make_validation_err({"client_ids":["El campo client_ids es obligatorio"],"client_ids.0":["El cliente debe ser tipo mayorista"]}),make_not_found("Producto"),make_auth_err()]
    elif "exclusions" in name and method=="DELETE":
        item["response"]=[{"name":"200 - Exclusiones eliminadas","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Exclusiones eliminadas","data":{"product_id":15,"removed_client_ids":[3,7]}},ensure_ascii=False,indent=2)},make_not_found("Producto"),make_auth_err()]
def walk_p(items):
    for it in items:
        if "item" in it: walk_p(it["item"])
        else: fill_products(it)
walk_p(products["item"])
with open(products_path,"w",encoding="utf-8") as f:
    json.dump(products,f,ensure_ascii=False,indent="	")
print("Products done")
# CADETE
cadete_path=os.path.join(postman_dir,"Cadete.postman_collection.json")
with open(cadete_path,encoding="utf-8") as f: cadete=json.load(f)
for item in cadete["item"]:
    if item.get("response")==[]:
        item["response"]=[{"name":"200 - Entrega registrada","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Entrega registrada correctamente","data":{"id":142,"sale_status_id":4,"status":{"id":4,"name":"Entregado"},"receiver_name":"Maria Lopez","receiver_dni":"30123456","receiver_observations":"Entregado en porteria","updated_at":"2026-05-26T14:00:00.000000Z"}},ensure_ascii=False,indent=2)},{"name":"403 - Sin permiso (no es el cadete asignado)","status":"Forbidden","code":403,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"No tenes permiso para marcar este pedido como entregado"},ensure_ascii=False,indent=2)},{"name":"422 - Error de validacion","status":"Unprocessable Entity","code":422,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Error de validacion","errors":{"receiver_name":["El campo nombre del receptor es obligatorio"],"receiver_dni":["El campo DNI del receptor es obligatorio"]}},ensure_ascii=False,indent=2)},make_not_found("Pedido"),make_auth_err()]
with open(cadete_path,"w",encoding="utf-8") as f:
    json.dump(cadete,f,ensure_ascii=False,indent="	")
print("Cadete done")
# MERCADOPAGO
mp_path=os.path.join(postman_dir,"MercadoPago.postman_collection.json")
with open(mp_path,encoding="utf-8") as f: mp=json.load(f)
mp_resp={
    "POST v1/mercadopago/create-preference":[{"name":"200 - Preferencia creada","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Preferencia creada","data":{"preference_id":"1088548843-abcd1234-5678-90ab-cdef12345678","init_point":"https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1088548843-abcd1234","sandbox_init_point":"https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=1088548843-abcd1234"}},ensure_ascii=False,indent=2)},{"name":"400 - Pedido no esta en estado pendiente de pago","status":"Bad Request","code":400,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"El pedido no esta en estado pendiente de pago"},ensure_ascii=False,indent=2)},make_validation_err({"sale_id":["El campo sale_id es obligatorio"]}),make_not_found("Pedido"),make_auth_err()],
    "POST mercadopago/webhook":[{"name":"200 - Webhook procesado (pago aprobado)","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Pago aprobado. Pedido actualizado a estado Aprobado.","sale_id":142,"sale_status_id":1},ensure_ascii=False,indent=2)},{"name":"200 - Webhook procesado (pago rechazado)","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Pago rechazado. Pedido actualizado a estado Pago rechazado.","sale_id":142,"sale_status_id":9},ensure_ascii=False,indent=2)},{"name":"200 - Evento ignorado (no es de tipo payment)","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Evento ignorado"},ensure_ascii=False,indent=2)}],
    "GET mercadopago/success":[{"name":"200 - Retorno exitoso de checkout","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"status":"approved","payment_id":"123456789","external_reference":"142","collection_status":"approved"},ensure_ascii=False,indent=2)}],
    "GET mercadopago/failure":[{"name":"200 - Retorno fallido de checkout","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"status":"rejected","payment_id":"123456789","external_reference":"142","collection_status":"rejected"},ensure_ascii=False,indent=2)}],
    "GET mercadopago/pending":[{"name":"200 - Retorno pendiente de checkout","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"status":"pending","payment_id":"123456789","external_reference":"142","collection_status":"pending"},ensure_ascii=False,indent=2)}]
}
for item in mp["item"]:
    if item.get("response")==[]: item["response"]=mp_resp.get(item["name"],[])
with open(mp_path,"w",encoding="utf-8") as f:
    json.dump(mp,f,ensure_ascii=False,indent="	")
print("MercadoPago done")
# PDF DIRECTORIES
pdf_path=os.path.join(postman_dir,"PdfDirectories.postman_collection.json")
with open(pdf_path,encoding="utf-8") as f: pdf=json.load(f)
pdf_resp={
    "GET pdf-directories":[{"name":"200 - Lista de carpetas de PDF","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"Directorios obtenidos","data":[{"date":"26-05-2026","date_formatted":"26/05/2026","total_pdfs":12,"user":{"id":2,"name":"Maria","lastName":"Lopez"}},{"date":"25-05-2026","date_formatted":"25/05/2026","total_pdfs":8,"user":{"id":2,"name":"Maria","lastName":"Lopez"}}],"metaData":{"current_page":1,"last_page":3,"per_page":15,"total":42}},ensure_ascii=False,indent=2)},make_auth_err()],
    "GET pdf-directories/{fecha}":[{"name":"200 - PDFs de la fecha","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"PDFs obtenidos","data":{"date":"26-05-2026","pdfs_pedidos":[{"name":"142-etiquetas.pdf","sale_id":142,"size":"245KB"},{"name":"143-stickers.pdf","sale_id":143,"size":"189KB"}],"extras":{"cintas_coser":[],"cintas_planchar":[{"name":"cintas-26052026.pdf","size":"120KB"}],"bandas":[],"sellos":[]}}},ensure_ascii=False,indent=2)},make_not_found("Directorio"),make_auth_err()],
    "GET pdf-directories/{fecha}/download-zip":[{"name":"200 - ZIP descargado","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/zip"},{"key":"Content-Disposition","value":"attachment; filename=pdfs-26-05-2026.zip"}],"body":"(ZIP binario con sub-carpetas: pedidos/, cintas_coser/, cintas_planchar/, bandas/, sellos/)"},make_not_found("Directorio"),make_auth_err()],
    "GET pdf-directories/{fecha}/download/{nombrePdf}":[{"name":"200 - PDF individual descargado","status":"OK","code":200,"header":[{"key":"Content-Type","value":"application/pdf"},{"key":"Content-Disposition","value":"attachment; filename=142-etiquetas.pdf"}],"body":"(PDF binario)"},{"name":"403 - Sin permiso (el disenador no tiene acceso a este PDF)","status":"Forbidden","code":403,"header":[{"key":"Content-Type","value":"application/json"}],"body":json.dumps({"message":"No tenes permiso para descargar este PDF"},ensure_ascii=False,indent=2)},make_not_found("PDF"),make_auth_err()]
}
for item in pdf["item"]:
    if item.get("response")==[]: item["response"]=pdf_resp.get(item["name"],[])
with open(pdf_path,"w",encoding="utf-8") as f:
    json.dump(pdf,f,ensure_ascii=False,indent="	")
print("PdfDirectories done")
print("All done!")
