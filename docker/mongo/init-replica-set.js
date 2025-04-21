// Configuração do Replica Set.
// O _id DEVE ser o mesmo passado em --replSet
// O host DEVE ser o nome do serviço docker ('mongo') e a porta interna (27017)
let config = {
    "_id": "rs0",
    "members": [
        { "_id": 0, "host": "127.0.0.1:27017" }
    ]
};

let initiated = false;
let retries = 15; // Número de tentativas
let waitTime = 3000; // Espera em ms entre tentativas (2 segundos)

print("Starting replica set initiation script...");

while (retries > 0 && !initiated) {
    try {
        print(`Attempting rs.initiate() (Retries left: ${retries})...`);
        // Tenta iniciar diretamente
        let result = rs.initiate(config);
        printjson(result); // Imprime o resultado da inicialização
        if (result.ok === 1) {
            print("Replica set initiated successfully.");
            initiated = true; // Marca como iniciado
        } else {
            print("rs.initiate() command failed, but didn't throw an exception. Retrying...");
            // Poderia haver um erro não excepcional, vamos tentar novamente.
        }
    } catch (e) {
        // Captura o erro específico se já estiver inicializado
        if (e.codeName === 'AlreadyInitialized' || e.code === 23 /* AlreadyInitialized */ || (e?.message?.includes("already initialized"))) {
            print("Replica set already initialized (caught exception).");
            initiated = true; // Marca como iniciado, pois já existe
        } else {
            // Outros erros podem ser temporários enquanto o mongod ainda está subindo
            print(`Caught error during rs.initiate(): ${e?.message ?? JSON.stringify(e)}. Retrying...`);
            printjson(e); // Imprime o erro para debug
        }
    }

    if (!initiated) {
        retries--;
        if (retries > 0) {
            sleep(waitTime); // Espera antes de tentar novamente
        } else {
            print("Max retries reached for rs.initiate(). Giving up.");
            // Descomentar para lançar um erro aqui e falhar a inicialização do container
            // throw new Error("Failed to initialize replica set after multiple retries.");
        }
    }
}

// Pequena pausa final opcional
if (initiated) {
    print("Waiting a bit more for primary election...");
    sleep(15000); // Espera 3 segundos adicionais
}

print("Replica set initialization script finished.");
