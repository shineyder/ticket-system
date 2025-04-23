// Configuração do Replica Set.
// O _id DEVE ser o mesmo passado em --replSet
// O host DEVE ser o nome do serviço docker ('mongo') e a porta interna (27017)
let config = {
    "_id": "rs0",
    "members": [
        { "_id": 0, "host": "mongo:27017" }
    ]
};

let initiated = false;
let retries = 15; // Número de tentativas
let waitTime = 4000; // Espera em ms entre tentativas (4 segundos)

print("MONGO-INIT: Starting replica set initiation attempt from init container...");

while (retries > 0 && !initiated) {
    try {
        print(`MONGO-INIT: Attempting rs.initiate() with host "mongo:27017" (Retries left: ${retries})...`);
        let result = rs.initiate(config);
        printjson(result);
        if (result.ok === 1) {
            print("MONGO-INIT: Replica set initiated successfully.");
            initiated = true;
        } else if (result.errmsg?.includes("already initialized")) {
             print("MONGO-INIT: Replica set already initialized (detected by errmsg).");
             initiated = true;
        } else {
             print(`MONGO-INIT: rs.initiate() command failed with ok: ${result.ok}, errmsg: ${result.errmsg}. Retrying...`);
        }
    } catch (e) {
        if (e.codeName === "AlreadyInitialized" || e.code === 23 || e.message?.includes("already initialized")) {
            print("MONGO-INIT: Replica set already initialized (caught exception).");
            initiated = true;
        } else {
            print(`MONGO-INIT: Caught error during rs.initiate(): ${e.message || JSON.stringify(e)}. Retrying...`);
        }
    }

    if (!initiated) {
        retries--;
        if (retries > 0) {
            print(`MONGO-INIT: Waiting ${waitTime / 1000} seconds before next attempt...`);
            sleep(waitTime);
        } else {
            print("MONGO-INIT: Max retries reached for rs.initiate(). Exiting init container with failure.");
            // Força o container a sair com erro se não conseguir iniciar
            quit(1); // Sai do mongosh com código de erro
        }
    }
}
print("MONGO-INIT: Replica set initialization script finished successfully.");
quit(0); // Sai do mongosh com sucesso
