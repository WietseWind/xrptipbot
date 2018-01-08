const RippleAPI = require('ripple-lib').RippleAPI
const api       = new RippleAPI({ server: 'wss://s1.ripple.com' }) // Public rippled server
const fetch     = require('node-fetch')
const fs        = require('fs')

let rawSecretData = fs.readFileSync('/data/.config.js');
let wallets       = JSON.parse(rawSecretData).secrets

var closedLedger = 0
var fee = 0
var startLedger = 0

/* * * * * * * * * * */

  var feeLimit = 100
  var ledgerAwait = 5

  var argv = process.argv.reverse()[0].split(':')

  if(argv.length !== 4) {
    console.log('Invalid # arguments')
    process.exit(1)
  }

  var payFrom = argv[1]
  var payTo = argv[2]
  var payTo_tag = parseInt(argv[3])
  var xrpAmount = parseFloat(argv[0])

  console.log('< PAY ' + xrpAmount + ' XRP FROM ' + payFrom + ' TO ' + payTo + ':' + payTo_tag)

  var signed_tx = null
  var tx_at_ledger = 0
  var tx_result = null

/* * * * * * * * * * */

var _processTransaction = function () {
  var _fee = fee
  if(fee > feeLimit) _fee = feeLimit

  console.log('>> Getting account info for: ', payFrom)
  api.getAccountInfo(payFrom).then(function(accInfo){
    console.log('<< [getAccountInfo] done, info: ', accInfo)

    var transaction = {
        "TransactionType" : "Payment",
        "Account" : payFrom,
        "Fee" : _fee+"",
        "Destination" : payTo,
        "DestinationTag" : payTo_tag,
        "Amount" : (xrpAmount*1000*1000)+"",
        "LastLedgerSequence" : closedLedger+ledgerAwait,
        "Sequence" : accInfo.sequence
     }

    // transaction = {"tx_json" : transaction }
    console.log('===> Transaction: ', transaction)

    var txJSON = JSON.stringify(transaction)
    var secret = Object.keys(wallets)[Object.values(wallets).indexOf(payFrom)]

    if(typeof secret === 'undefined') {
      console.log('-- ERROR RETRIEVING SECRET')
      process.exit(1)
    }

    signed_tx = api.sign(txJSON, secret)
    tx_at_ledger = closedLedger

    console.log('===> Signed TX << ID >>: ', signed_tx.id)

    console.log('-------- SUBMITTING TRANSACTION --------')
    api.submit(signed_tx.signedTransaction).then(function(tx_data){
      console.log(tx_data)

      console.log('   >> [Tentative] Result: ', tx_data.resultCode)
      console.log('   >> [Tentative] Message: ', tx_data.resultMessage)
    }).catch(function(e){
      console.log('-- ERROR SUBMITTING TRANSACTION: ', e)
      process.exit(1)
    })
  })
}

var _lastClosedLedger = function (ledgerIndex) {
  var i = parseInt(ledgerIndex)
  if (ledgerIndex > closedLedger) {
    if(startLedger < 1) {
      startLedger = ledgerIndex
    }

    closedLedger = ledgerIndex
    console.log('# LEDGER CLOSED: ', closedLedger)

    if(closedLedger > startLedger + ledgerAwait + 1){
      console.log('-- TIMEOUT AFTER # LEDGERS (+1): ', ledgerAwait)
      process.exit(1)
    }

    if(signed_tx !== null && tx_result === null && (closedLedger-tx_at_ledger) <= ledgerAwait){
      api.getTransaction(signed_tx.id, {
        minLedgerVersion: tx_at_ledger,
        maxLedgerVersion: closedLedger
      }).then(function(d){
        console.log('<< TX RESPONSE @ L:' + (closedLedger-tx_at_ledger) + '] >> ', d)
        tx_result = d
        process.exit(0)
      }).catch(function(e,x){
      })
    }else{
      if(signed_tx !== null && (closedLedger-tx_at_ledger) > ledgerAwait){
        console.log('TX FAILED')
        process.exit(1)
      }
    }
  }
}

var _bootstrap = function () {

  api.connection._ws.on('message', function(m){
    var message = JSON.parse(m)
    if (message.type === 'ledgerClosed') {
      _lastClosedLedger(message.ledger_index)
    }else{
      if (message.type !== 'response') {
        console.log('    # < msg ', message.type)
      }
    }
  })

  api.connection.request({
    command: 'subscribe',
    accounts: Object.values(wallets)
  })

  api.connection.request({
    command: 'subscribe',
    streams: [ 'ledger' ]
  })

  return

} /* end _bootstrap */

api.on('error', (errorCode, errorMessage) => {
  console.log(errorCode + ': ' + errorMessage)
  process.exit(1);
})
api.on('connected', () => {
  console.log('<< connected >>');
})
api.on('disconnected', (code) => {
  console.log('<< disconnected >> code:', code)
  process.exit(1);
})
api.connect().then(() => {
// Connected
  api.getServerInfo().then(function (server) {
    fee = parseFloat(server.validatedLedger.baseFeeXRP)*1000*1000
    console.log('Server base fee: ', fee)
    _lastClosedLedger(server.validatedLedger.ledgerVersion)
    _bootstrap()

    // Todo: promises, bootstrap, lastClosedLedger, processTransaction
    api.getFee().then(function(e){
      _fee = parseFloat(e)*1000*1000
      if(_fee !== fee && parseFloat(_fee) > parseFloat(fee)){
        fee = _fee
        console.log('New estimated fee: ', fee)
        _processTransaction()
      }
    })
  })
}).then(() => {
  // return api.disconnect()
}).catch(console.error)


// setTimeout(function(){
//   process.exit(1);
// }, 5000);
