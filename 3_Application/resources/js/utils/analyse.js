// Analyse automatique d'une demande (contraintes produites par app/Support/AnalyseDemande.php).
// À garder en phase avec AnalyseDemande::verdict, niveau, contraintesRelevees et conditions.

export const VERDICTS = {
    compatible: { label: 'Compatible', icone: 'check-circle' },
    conditions: { label: 'Compatible sous conditions', icone: 'warning' },
    conflit: { label: 'Conflit détecté', icone: 'prohibit' },
};

// Les analyses enregistrées avant le moteur n'ont que { type, message } : un avertissement compte comme un point d'attention.
export const niveau = (c) => c.niveau ?? (c.type === 'bloquant' ? 'bloquant' : 'attention');

export function verdictAnalyse(contraintes) {
    const niveaux = (contraintes ?? []).map(niveau);
    if (niveaux.includes('bloquant') || niveaux.includes('conflit')) return 'conflit';
    return niveaux.includes('attention') ? 'conditions' : 'compatible';
}

// Contraintes qui pèsent sur le verdict, sans les simples informations
export const contraintesRelevees = (contraintes) => (contraintes ?? []).filter((c) => niveau(c) !== 'info');

// Conditions proposées, sans doublon
export const conditionsAnalyse = (contraintes) => [...new Set((contraintes ?? []).map((c) => c.condition).filter(Boolean))];
