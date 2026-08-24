<?php
return [
    'tab'=>'Sinistri','title'=>'Sinistri','register'=>'Registra sinistro','edit'=>'Modifica sinistro','loading'=>'Caricamento sinistri...','save_first'=>'Salva prima il contratto/noleggio per registrare sinistri.','empty'=>'Nessun sinistro registrato.','load_error'=>'Impossibile caricare i sinistri.','save_error'=>'Impossibile salvare il sinistro.','charge_error'=>'Impossibile creare l’addebito.','required'=>'Compila data, veicolo, tipo e descrizione.','charge_required'=>'Compila importo, scadenza, conto e metodo di pagamento.','charge_title'=>'Crea addebito del sinistro','charge_created'=>'Addebito creato con successo.','view_charge'=>'Vedi addebito','edit_action'=>'Modifica','generate_charge_action'=>'Crea addebito','not_generated'=>'Non creato','saved'=>'Sinistro salvato con successo.',
    'fields'=>['date'=>'Data e ora','vehicle'=>'Veicolo','type'=>'Tipo','estimated_value'=>'Valore stimato','description'=>'Descrizione','status'=>'Stato','notes'=>'Note','charge'=>'Addebito','actions'=>'Azioni'],
    'types'=>['collision'=>'Collisione/incidente','theft'=>'Furto/rapina','fire'=>'Incendio','flood'=>'Allagamento','third_party'=>'Danni a terzi','total_loss'=>'Perdita totale','other'=>'Altro'],
    'status'=>['open'=>'Aperto','completed'=>'Concluso'],
    'charge'=>['generate'=>'Crea addebito al cliente','value'=>'Importo','due_date'=>'Scadenza','account'=>'Conto bancario','payment_method'=>'Metodo di pagamento','pending'=>'In sospeso','paid'=>'Pagato'],
    'report'=>['title'=>'Sinistri','description'=>'Sinistri registrati per contratti e noleggi, con importi e stato degli addebiti.','total'=>'Totale sinistri','open'=>'Aperti','completed'=>'Conclusi','charged_value'=>'Valore addebitato','client'=>'Cliente','link'=>'Collegamento'],
];
