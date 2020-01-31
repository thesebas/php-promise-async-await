# php-promise-async-await
ReactPHP async-await emulator with generator and yield


## Instalation

    composer require thesebas/promise-async-await
    
## Usage
It makes possible writing

    asyncRun(function ($init) {
        $res = yield asyncFunc($init);
        $res2 = yield all([asyncFunc($res+1), asyncFunc($res+1)]);
        $res3 = yield race([asyncFunc($res*2), asyncFunc($res2/2)]);
        return "wow".$init.$res.$res2.$res3;
    }, 0.1)->then(function($result){
        // ... $result is eq "wow".$res.$res2.$res3;
    });
    
instead of

    asyncFunc($init)->then(function($res){
        return all([asyncFunc($res+1), asyncFunc($res+1)]);
    })->then(function($res2){
        return yield race([asyncFunc(/* $res ?? */), asyncFunc($res2/2)]);
    })->then(function($res3){
        return "wow"./* $init ?? */ /* $res ??*/ /* $res2 ??*/ $res3
    })->then(function($result){
        // $result is eq "wow".$res3
    })
    
For more see `spec`
