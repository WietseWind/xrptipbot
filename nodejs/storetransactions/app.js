const RippleAPI = require('ripple-lib').RippleAPI
const api       = new RippleAPI({ server: 'wss://s1.ripple.com' }) // Public rippled server
const fetch     = require('node-fetch')

const fs        = require('fs')

let rawWalletData = fs.readFileSync('/data/.config.js');
let wallets       = JSON.parse(rawWalletData).wallets

var closedLedger = 0
var resetAppTimeout = null;

var _lastClosedLedger = function (ledgerIndex) {
  var i = parseInt(ledgerIndex)
  if (ledgerIndex > closedLedger) {
    closedLedger = ledgerIndex

    if(resetAppTimeout !== null) {
      clearTimeout(resetAppTimeout)
    }
    var s = 60
    resetAppTimeout = setTimeout(function(){
      console.log('!!! No closed ledger for ' + s + ' seconds! Resetting application (connection may be lost)')
    }, s*1000)

    console.log('# LEDGER CLOSED: ', closedLedger)
  }
}

var _storeTransaction = function (tx) {
  if (tx.ledger_index <= closedLedger) {
    var destinationTag = parseInt(tx.DestinationTag||0)
    var transferAmount = (parseFloat(typeof tx.Amount !== 'undefined' ? tx.Amount : 0)/1000/1000)

    var consolePostFix = ' [NO DESTINATION, NON PAYMENT? ESCROW?]'
    if (typeof tx.Destination !== 'undefined') {
      consolePostFix = ' To ' + tx.Destination + ':' + destinationTag + ' = ' + transferAmount
    }
    console.log(tx.hash + ' [ ' + tx.ledger_index + ' ] => From ' + tx.Account + consolePostFix)

    var transactionJson = JSON.stringify({
      hash: tx.hash,
      ledger: tx.ledger_index,
      from: tx.Account,
      to: tx.Destination,
      xrp: transferAmount,
      tag: destinationTag,
      type: tx.TransactionType,
      fullTx: JSON.parse(JSON.stringify(tx))
    })

    fetch('http://127.0.0.1/index.php/storetransaction', { method: 'POST', body: transactionJson })
      .then(function(res) {
        return res.json();
      }).then(function(json) {
        console.log(' --> Got response from [storetransaction] backend', json);
      });
  } else {
    console.log('Error: got transaction < lastClosedLedger (' + closedLedger + ')', tx)
  }
}

var _bootstrap = function () {
  api.connection._ws.on('message', function(m){
    var message = JSON.parse(m)
    if (message.type === 'ledgerClosed') {
      _lastClosedLedger(message.ledger_index)
    }
    if (message.type === 'response' && typeof message.id !== 'undefined' && message.id <= wallets.length) {
      if (typeof message.result.transactions !== 'undefined' && message.result.transactions.length > 0) {
        message.result.transactions.filter(function (f) {
          return f.validated && (f.tx.TransactionType === 'Payment' || f.tx.TransactionType === 'EscrowCreate' || f.tx.TransactionType === 'EscrowFinish') && f.meta.TransactionResult === 'tesSUCCESS'
        }).forEach(function (t) {
          var tx = t.tx
          _storeTransaction(tx)
        })
      }
    }
  })

  wallets.forEach(function (_w, k) {
    console.log('Processing history: @' + k, _w)
    api.connection._ws.send(JSON.stringify({
      id: k + 1,
      command: "account_tx", account: _w,
      ledger_index_min: -1, ledger_index_max: -1,
      binary: false, count: false, limit: 2000,
      descending: true
    }))
  })

  api.connection.on('transaction', (t) => {
    if ((t.transaction.TransactionType === 'Payment' || t.transaction.TransactionType === 'EscrowCreate' || t.transaction.TransactionType === 'EscrowFinish') && t.meta.TransactionResult === 'tesSUCCESS') {
      var tx = t.transaction
      tx.ledger_index = t.ledger_index
      _storeTransaction(tx)
    }
  })

  api.connection.request({
    command: 'subscribe',
    streams: [ 'ledger' ]
  })

  return api.connection.request({
    command: 'subscribe',
    accounts: wallets
  })

} /* end _bootstrap */

api.on('error', (errorCode, errorMessage) => {
  console.log(errorCode + ': ' + errorMessage)
  process.exit(1);
})
api.on('connected', () => {
  console.log('<< connected >>');
})
api.on('disconnected', (code) => {
  // code - [close code](https://developer.mozilla.org/en-US/docs/Web/API/CloseEvent) sent by the server
  // will be 1000 if this was normal closure
  console.log('<< disconnected >> code:', code)
  process.exit(1);
})
api.connect().then(() => {
// Connected
  api.getServerInfo().then(function (server) {
    _lastClosedLedger(server.validatedLedger.ledgerVersion)
    _bootstrap()
  })
}).then(() => {
  // return api.disconnect()
}).catch(console.error)


// setTimeout(function(){
//   process.exit(1);
// }, 5000);
