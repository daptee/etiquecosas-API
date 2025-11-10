empresa = {"nombre":None, "direccion":None, "provincia":None, 'email':None}
provincias = ["BUE", "COR", "SFE", "SGO", "TUC", "TDF", "CBA", "ENR", "CHC", "SAL", "JUY", "FOR", "MIS", "CDB", "CHB", "SCZ", "RNE", "LPA", "SLU", "LRJ", "CAT"]

def validar_nombre(nombre_ingresado):
    if nombre_ingresado[0] >= "a" and nombre_ingresado[0] <= "z":
        primera_letra = chr(ord(nombre_ingresado[0]) - 32)
        nombre_ingresado[0] = primera_letra
        nombre_parseado = ""
        for letra in nombre_ingresado:
            nombre_parseado += letra  
        return nombre_parseado
    else:
        print("El formato de nombre es incorrecto.")

def validar_direccion(direccion_ingresada):
    if direccion_ingresada[0] >= "a" and direccion_ingresada[0] <= "z":
        primera_letra = chr(ord(direccion_ingresada[0]) - 32)
        direccion_ingresada[0] = primera_letra  
        direccion_parseada = ""
        for letra in direccion_ingresada:
            direccion_parseada += letra    
            return direccion_parseada
    else:
        print("El formato de la direccion es incorrecto.")      

def validar_provincia(provincia_ingresada):
    if provincia_ingresada not in provincias:
        print("La provincia ingresada no existe.")
    else:
        return provincia_ingresada    

def validar_email(email_ingresado):
    if "@" not in email_ingresado:      
        print("El formato ingresado no corresponde a un E-Mail.")  
    else:
        return email_ingresado    

opcion = 1
while opcion != 0:
    nombre_ingresado = list(input("Ingrese el nombre: "))
    nombre = validar_nombre(nombre_ingresado)
    direccion_ingresada = list(input("Ingrese la direccion: "))
    direccion = validar_direccion(direccion_ingresada)
    provincia_ingresada = input("Ingrese la provincia (Formato 3 letras): ")
    provincia = validar_provincia(provincia_ingresada)
    email_ingresado = input("Ingrese el E-Mail: ")
    email = validar_email(email_ingresado) 
    empresa["nombre"] = nombre
    empresa["direccion"] = direccion
    empresa["provincia"] = provincia
    empresa["email"] = email
    print("Empresa actualizada.")
    print(f"Nombre: {empresa['nombre']}")
    print(f"Direccion: {empresa['direccion']}")
    print(f"Provincia: {empresa['provincia']}")
    print(f"E-Mail: {empresa['email']}")
    opcion = int(input("¿Desea modificar los datos de la empresa (SI:1-9 | NO:0): "))

lista = []
direccionario = {"clave":"valor"}
lista.append(direccionario)