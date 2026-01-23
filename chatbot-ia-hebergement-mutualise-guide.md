# Guide complet : Chatbot IA sur hébergement mutualisé

## Contexte du projet

Bruno souhaite installer un chatbot avec intelligence artificielle sur un hébergement mutualisé. Ce document récapitule toutes les informations, décisions et stratégies retenues pour ce projet.

---

## 1. Possibilité d'installation sur hébergement mutualisé

### ✅ C'est possible, mais avec limitations

**Solutions compatibles :**
- Chatbots basés sur JavaScript (exécution côté client/navigateur)
- Chatbots via widgets tiers (Tidio, Drift, Intercom, Crisp)
- Chatbots PHP basiques

**Limitations de l'hébergement mutualisé :**
- Pas d'accès SSH ou de contrôle serveur complet
- Impossible d'installer des frameworks nécessitant Node.js, Python, ou autres environnements spécifiques
- Ressources CPU/RAM très limitées (128-512 Mo généralement)
- Pas de websockets en temps réel dans la plupart des cas

### ❌ Impossibilité d'héberger une IA localement

**Pourquoi les IA légères ne fonctionnent pas sur mutualisé :**

**Ressources nécessaires** - Même les modèles les plus légers requièrent :
- Plusieurs Go de RAM (minimum 2-4 Go)
- GPU ou CPU puissant pour l'inférence
- Plusieurs Go d'espace disque pour les fichiers du modèle

**Environnement technique** - Les modèles IA nécessitent :
- Python avec bibliothèques spécifiques (PyTorch, TensorFlow, transformers)
- Node.js ou autres environnements non-PHP
- Accès système que le mutualisé ne permet pas

**Performance** - Même si c'était techniquement possible :
- Un modèle IA léger prendrait 5-30 secondes pour répondre
- Expérience utilisateur inutilisable
- Risque de saturation des ressources serveur

---

## 2. Solution retenue : Architecture hybride

**Principe :**
- Frontend et interface web → Hébergement mutualisé
- Intelligence artificielle → APIs externes (hébergées ailleurs)
- Communication via appels HTTP/HTTPS

**Avantages :**
- Pas de limitation de ressources pour l'IA
- Modèles puissants et rapides
- Mise à jour automatique des modèles
- Paiement à l'usage ou gratuit selon services

**Schéma de fonctionnement :**
```
Visiteur du site
    ↓
Site web (hébergement mutualisé)
    ↓
Script PHP ou JavaScript
    ↓
API IA externe (Groq, Gemini, etc.)
    ↓
Réponse renvoyée au visiteur
```

---

## 3. Services IA disponibles et comparaison

### Services IA gratuits/peu coûteux étudiés

#### **Groq** ⭐ Service principal recommandé

**Caractéristiques :**
- Ultra rapide (inférence en quelques millisecondes)
- Utilise des modèles open source (Llama, Mixtral, Gemma)
- Excellent support multilingue dont français

**Offre gratuite :**
- 30 requêtes par minute
- 14 400 requêtes par jour
- 14 400 tokens par minute
- 1 000 000 tokens par jour
- 10 requêtes simultanées maximum

**Modèles disponibles gratuitement :**
- Llama 3.1 (8B, 70B, 405B)
- Llama 3.3 (70B)
- Mixtral 8x7B
- Gemma 2 (9B, 27B)

**Limitations :**
- Pas de garantie de disponibilité (best effort)
- Peut être plus lent aux heures de pointe
- Pas de support prioritaire
- Politique d'usage raisonnable

**Plan payant (si besoin futur) :**
- Pay-as-you-go (paiement à l'usage)
- Environ 0,10-0,70 dollars par million de tokens selon modèle
- Limites beaucoup plus élevées

---

#### **Gemini** (Google AI) ⭐ Service backup recommandé

**Caractéristiques :**
- Excellent support multilingue (français inclus)
- Très bon avec contexte long
- Multimodal (texte + images)
- Intégration Google facilitée

**Offre gratuite :**
- 15 requêtes par minute
- 1 500 requêtes par jour
- 1 000 000 tokens par minute (énorme !)

**Modèles disponibles gratuitement :**
- Gemini 1.5 Flash (rapide, léger)
- Gemini 1.5 Pro (plus puissant)
- Gemini 2.0 Flash (le plus récent)

**Points forts :**
- Très généreux en tokens (1 million/minute)
- Excellente qualité en français
- Capacité multimodale unique

**Points faibles :**
- Moins de requêtes/jour que Groq (1 500 vs 14 400)
- Moins de requêtes/minute que Groq (15 vs 30)

---

#### **Autres services disponibles**

**Cohere :**
- 1000 appels API par mois gratuitement
- Pas besoin de carte bancaire pour tester
- Support multilingue (français)
- Prix compétitifs après gratuit
- Modèles : Generate, Command, Embed, Rerank

**Mistral AI** (français 🇫🇷) :
- Entreprise française spécialisée IA
- Excellents modèles pour le français
- Offre gratuite limitée
- API professionnelle
- Prix raisonnables

**Hugging Face Inference API :**
- Offre gratuite généreuse (avec rate limits)
- Accès à des milliers de modèles open source
- Modèles français excellents (Mistral, Vigogne)
- Idéal pour tester différents modèles
- Gratuit mais limité en requêtes/heure

**Together AI :**
- Spécialisé dans modèles open source
- Prix très compétitifs
- Bons modèles français (Mistral)
- Crédits gratuits au démarrage
- API facile à utiliser

**Replicate :**
- Paiement à l'usage uniquement
- Accès à nombreux modèles (Llama, Mistral)
- Pas d'abonnement mensuel
- Quelques centimes par requête
- Bon pour usage occasionnel

**AI21 Labs :**
- Modèles Jurassic
- Offre gratuite d'essai
- Bonne qualité mais moins connu
- Prix moyens

---

### Tableau comparatif des services principaux

| Solution | Gratuit | Qualité français | Vitesse | Facilité | Requêtes/jour |
|----------|---------|------------------|---------|----------|---------------|
| Groq | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | 14 400 |
| Gemini | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | 1 500 |
| Cohere | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ~33 |
| Mistral AI | ⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | Variable |
| Hugging Face | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐ | Variable |
| Together AI | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | Variable |

---

### Comparaison Groq vs Gemini (les 2 services retenus)

| Critère | Groq | Gemini |
|---------|------|--------|
| Requêtes/minute | 30 | 15 |
| Requêtes/jour | 14 400 | 1 500 |
| Tokens/minute | 14 400 | 1 000 000 |
| Vitesse | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| Qualité français | ⭐⭐⭐ | ⭐⭐⭐⭐ |
| Multimodal | ❌ | ✅ (texte + images) |

**Conclusion :**
- **Groq** : Meilleur si beaucoup de visiteurs (plus de requêtes/jour)
- **Gemini** : Meilleur si conversations longues (plus de tokens) et besoin d'images

---

## 4. Différence entre requêtes et tokens

### Définition des requêtes

**Requête (Request) = 1 appel à l'API**, peu importe la longueur du texte

**Exemples :**
- Visiteur dit "Bonjour" → 1 requête
- Visiteur dit "Peux-tu m'expliquer en détail toute l'histoire de France depuis la préhistoire jusqu'à aujourd'hui avec tous les détails possibles ?" → 1 requête aussi

**Donc : 1 requête = 1 échange**, quelle que soit la taille du message.

---

### Définition des tokens

**Token = un morceau de mot** (environ 4 caractères en français, 3-4 lettres)

**Exemples de découpage :**
- "Bonjour" = environ 2 tokens
- "Chatbot" = environ 2 tokens
- "Intelligence artificielle" = environ 5 tokens
- Une phrase de 100 mots = environ 130-150 tokens en français

**Règle approximative :**
- 1 token ≈ 4 caractères en français
- 100 mots ≈ 130-150 tokens
- 1000 caractères ≈ 250 tokens

---

### Calcul des tokens par requête

**À chaque appel API, les tokens comptabilisés sont :**

```
Tokens totaux = Tokens INPUT + Tokens OUTPUT

INPUT = Question/message envoyé à l'IA (y compris historique)
OUTPUT = Réponse générée par l'IA
```

**Exemple concret :**

Message utilisateur : "Quel temps fait-il ?" (5 tokens)
Réponse IA : "Il fait beau et ensoleillé aujourd'hui avec 22 degrés." (15 tokens)

**Résultat :**
- 1 requête consommée
- 20 tokens consommés (5 input + 15 output)

---

### Pourquoi cette double limite ?

**Limite de requêtes :** 
- Évite le spam et les abus
- Empêche quelqu'un de faire 1000 appels en 1 minute
- Protège le service contre la surcharge

**Limite de tokens :**
- Évite l'abus de ressources de calcul
- Empêche des requêtes géantes qui satureraient le système
- Contrôle la consommation réelle de puissance de calcul

---

### Application pratique pour le chatbot

**Avec Groq gratuit :**
- 30 requêtes/min = 30 visiteurs peuvent poser 1 question par minute
- 14 400 tokens/min = si chaque échange fait 500 tokens, environ 28 échanges par minute possible

**Avec Gemini gratuit :**
- 15 requêtes/min = 15 visiteurs peuvent poser 1 question par minute
- 1 000 000 tokens/min = énorme ! On atteint la limite de requêtes bien avant celle des tokens

**Conclusion importante :**
Pour un chatbot classique, on atteint généralement **la limite de requêtes AVANT celle des tokens**, sauf si on fait des conversations très longues avec beaucoup d'historique.

---

## 5. Stratégie multi-API : cumul des quotas

### Principe du cumul

**Chaque service a ses propres compteurs indépendants**

Les quotas ne se partagent PAS entre services → on peut réellement les cumuler !

**Exemple :**
- Groq : 30 req/min + 14 400 tokens/min
- Gemini : 15 req/min + 1 000 000 tokens/min
- Cohere : 1000 req/mois

**En utilisant les 3 simultanément, on ne touche pas aux quotas des autres.**

---

### Stratégies de cumul possibles

#### 1. Rotation simple (Round-robin)
Alternance entre les services dans un ordre fixe.

```
Requête 1 → Groq
Requête 2 → Gemini
Requête 3 → Cohere
Requête 4 → Groq (on recommence)
```

**Avantages :** Répartition équitable
**Inconvénients :** Pas d'optimisation par type de requête

---

#### 2. Fallback (Solution de secours) ⭐ **RECOMMANDÉ**

Utilise toujours le service principal, bascule sur backup si limite atteinte.

```
Toujours essayer Groq (le plus rapide)
    ↓ (si limite atteinte erreur 429)
Basculer automatiquement sur Gemini
    ↓ (si limite atteinte)
Basculer sur Cohere (dernier recours)
```

**Avantages :**
- Utilise le service préféré/plus rapide en priorité
- Haute disponibilité garantie
- Simple à implémenter

**Inconvénients :**
- Service principal peut être plus sollicité

---

#### 3. Selon le type de requête

Choix intelligent du service selon la nature de la question.

```
Questions courtes/simples → Groq (ultra rapide)
Questions longues/contexte important → Gemini (plus de tokens)
Questions complexes nécessitant raisonnement → Mistral
Questions avec images → Gemini (seul multimodal)
```

**Avantages :** Optimisation maximale selon besoin
**Inconvénients :** Logique complexe à implémenter

---

#### 4. Load balancing intelligent

Vérifie les quotas restants en temps réel et choisit le meilleur service disponible.

**Principe :**
- Vérifie combien de requêtes restent sur chaque service
- Choisit celui qui a le plus de marge
- Répartit intelligemment la charge

**Avantages :** Utilisation optimale des quotas
**Inconvénients :** Complexe, nécessite tracking précis

---

### Capacité totale cumulée (services gratuits)

**Avec Groq + Gemini + Cohere :**

**Par jour :**
- Groq : 14 400 requêtes
- Gemini : 1 500 requêtes
- Cohere : 33 requêtes (environ 1000/mois ÷ 30 jours)
- **TOTAL : environ 15 933 requêtes par jour**

**Par minute :**
- Groq : 30 requêtes/min
- Gemini : 15 requêtes/min
- Cohere : limité au mois
- **TOTAL : 45 requêtes/min** si on alterne

**Par minute (tokens) :**
- Groq : 14 400 tokens/min
- Gemini : 1 000 000 tokens/min
- **TOTAL : 1 014 400 tokens/min**

---

### Avantages de la stratégie multi-API

✅ Multiplie considérablement les capacités gratuites
✅ Haute disponibilité (si un service est down, les autres prennent le relais)
✅ Optimisation des coûts (reste gratuit plus longtemps)
✅ Flexibilité (possibilité de tester différents modèles)
✅ Résilience (pas de point unique de défaillance)

### Inconvénients

❌ Code plus complexe à maintenir
❌ Cohérence des réponses peut varier entre modèles IA
❌ Gestion de plusieurs clés API
❌ Nécessite tracking des quotas
❌ Debugging plus difficile

---

### Recommandation finale pour Bruno

**Configuration retenue : Groq (principal) + Gemini (backup)**

**Stratégie : Fallback automatique**
- Groq en priorité (ultra rapide, généreux)
- Gemini en backup (excellent français, énorme capacité tokens)
- Possibilité d'ajouter Cohere/Mistral plus tard si besoin

**Résultat :**
- Environ 16 000 requêtes par jour gratuitement
- 45 requêtes par minute en alternant
- Haute disponibilité garantie
- Simplicité d'implémentation

---

## 6. Gestion de l'historique de conversation

### Problématique fondamentale

**Les APIs d'IA n'ont AUCUNE mémoire entre les requêtes.**

Chaque appel API est totalement indépendant. Si tu ne fournis pas le contexte, l'IA ne saura pas de quoi on a parlé avant.

**Conséquence :**
Pour maintenir une conversation cohérente, il faut envoyer **TOUT l'historique** à chaque nouvelle requête.

---

### Architecture de gestion de l'historique

**Principe général :**

```
1. Stockage persistant (BDD ou session PHP)
   ↓
2. À chaque nouvelle question du visiteur :
   - Récupère TOUT l'historique précédent
   - Ajoute la nouvelle question
   - Envoie TOUT à l'API IA
   ↓
3. Reçoit la réponse de l'IA
   ↓
4. Stocke la réponse dans l'historique
   ↓
5. Affiche au visiteur
```

---

### Format standard de l'historique

**Format JSON utilisé par toutes les APIs IA :**

Structure de base d'un message :
```
{
  "role": "user" ou "assistant" ou "system",
  "content": "Le texte du message"
}
```

**Exemple d'historique complet :**

```
Conversation :
[
  {
    "role": "system",
    "content": "Tu es un assistant commercial spécialisé en hôtellerie"
  },
  {
    "role": "user",
    "content": "Bonjour, je cherche un hôtel à Paris"
  },
  {
    "role": "assistant",
    "content": "Bonjour ! Je peux vous aider. Quel est votre budget ?"
  },
  {
    "role": "user",
    "content": "Environ 150€ par nuit"
  },
  {
    "role": "assistant",
    "content": "Parfait, voici quelques suggestions dans cette gamme de prix..."
  }
]
```

**Rôles expliqués :**
- **system** : Instructions/contexte pour l'IA (optionnel, envoyé une seule fois au début)
- **user** : Messages du visiteur/utilisateur
- **assistant** : Réponses de l'IA

**Ce format est compatible avec :**
- OpenAI (GPT)
- Anthropic (Claude)
- Groq
- Gemini
- Cohere
- Mistral
- Pratiquement toutes les APIs IA modernes

---

### Options de stockage de l'historique

#### Option 1 : Session PHP (simple, petits volumes)

**Avantages :**
- Simple à mettre en place
- Pas besoin de base de données
- Rapide
- Automatiquement nettoyé après expiration session

**Inconvénients :**
- Perdu si session expire ou navigateur fermé
- Pas de persistance long terme
- Limité en taille (quelques Mo maximum)
- Impossible d'analyser les conversations passées
- Pas adapté si plusieurs serveurs (load balancing)

**Quand l'utiliser :**
- Chatbot simple avec peu de trafic
- Pas besoin de conserver l'historique
- Phase de test/développement

---

#### Option 2 : Base de données MySQL ⭐ **RECOMMANDÉ**

**Structure de table suggérée :**

```
Table : conversations

Colonnes :
- id : INT AUTO_INCREMENT PRIMARY KEY
- session_id : VARCHAR(255) - Identifiant unique de la conversation
- role : ENUM('user', 'assistant', 'system') - Qui parle
- content : TEXT - Contenu du message
- ai_service : VARCHAR(50) - Quel service IA a répondu (groq, gemini, etc.)
- tokens_used : INT - Nombre de tokens consommés (pour statistiques)
- created_at : TIMESTAMP DEFAULT CURRENT_TIMESTAMP - Date/heure du message

Index :
- INDEX sur session_id (pour récupération rapide)
- INDEX sur created_at (pour analyses temporelles)
```

**Avantages :**
- Persistance totale des conversations
- Possibilité d'analyses et statistiques
- Support de volumes importants
- Possibilité de reprendre conversation ultérieurement
- Tracking précis de l'utilisation
- Backup et récupération possibles

**Inconvénients :**
- Légèrement plus complexe à mettre en place
- Nécessite une base de données
- Consomme de l'espace disque

**Quand l'utiliser :**
- Production
- Besoin de conserver les conversations
- Analyse des usages
- Support client
- Amélioration continue du chatbot

---

### Gestion du basculement entre APIs

**Point crucial :** Peu importe quelle IA répond, elle doit avoir accès au même historique complet.

**Comment ça fonctionne avec le basculement :**

```
Conversation en cours :
Message 1-5 → Répondu par Groq
Message 6 → Groq atteint sa limite (erreur 429)
Message 6 → Automatiquement basculé sur Gemini

IMPORTANT : Gemini reçoit TOUT l'historique (messages 1-6)
            même si messages 1-5 ont été traités par Groq

Résultat : Gemini comprend le contexte et répond de manière cohérente
```

**Principe clé :**
L'historique est **agnostique du service IA**. On stocke juste les échanges user/assistant, peu importe quel service IA a généré la réponse.

**Avantage :**
Transition transparente entre services, l'utilisateur ne remarque rien.

---

## 7. PROBLÈME MAJEUR : Consommation des tokens par l'historique

### Le problème expliqué

**L'historique consomme des tokens à chaque requête !**

**Rappel du calcul des tokens :**
```
Tokens totaux par requête = INPUT + OUTPUT

INPUT = Historique complet + Nouvelle question
OUTPUT = Réponse de l'IA
```

**Exemple d'explosion des tokens :**

**Message 1 :**
- User : "Bonjour" (2 tokens)
- IA : "Bonjour ! Comment puis-je vous aider ?" (10 tokens)
- **Coût total : 2 + 10 = 12 tokens**

**Message 2 :**
- Historique à envoyer : 12 tokens
- User : "Quel temps fait-il ?" (5 tokens)
- IA : "Je ne peux pas vérifier la météo en temps réel" (15 tokens)
- **Coût total : 12 + 5 + 15 = 32 tokens**

**Message 3 :**
- Historique à envoyer : 32 tokens
- User : "D'accord, merci" (4 tokens)
- IA : "De rien, bonne journée !" (6 tokens)
- **Coût total : 32 + 4 + 6 = 42 tokens**

**Message 10 :**
- Historique cumulé : peut atteindre 500-1000 tokens !
- **Ça augmente exponentiellement ! 🚀**

---

### Impact réel sur les quotas

**Sans gestion/optimisation de l'historique :**

**Groq (14 400 tokens/min) :**
- Conversation moyenne de 10 messages = 500-1000 tokens
- Capacité réelle : environ 15-30 conversations par minute seulement
- Au lieu des 30 requêtes théoriques

**Avec optimisation :**
- Même quota de 14 400 tokens/min
- Historique maîtrisé à 100-200 tokens par requête
- Capacité réelle : 100-200 conversations par minute
- **Multiplication par 5-10 de la capacité !**

**Conclusion importante :**
Sans optimisation de l'historique, on divise la capacité réelle par 5 à 10 fois !

---

### Stratégies d'optimisation de l'historique

#### Stratégie 1 : Limite simple du nombre de messages

**Principe :**
Garder seulement les N derniers messages (ex: 10-12 derniers messages).

**Fonctionnement :**
- On garde les 12 derniers messages de l'historique
- On supprime les plus anciens
- Cela représente environ 6 échanges (user + assistant)

**Avantages :**
- Très simple à implémenter
- Efficace immédiatement
- Économie de 60-70% des tokens

**Inconvénients :**
- Perd le contexte ancien de la conversation
- Peut perdre des informations importantes mentionnées au début

**Quand l'utiliser :**
- Conversations courtes/moyennes
- Chatbot FAQ simple
- Phase de démarrage

**Paramètres recommandés :**
- MAX_HISTORY_MESSAGES = 12 (6 échanges)
- Ajustable selon le type de conversation

---

#### Stratégie 2 : Système de fenêtre glissante avec résumé

**Principe :**
Quand l'historique devient trop long, on résume les messages anciens.

**Fonctionnement :**
1. Quand on atteint 20+ messages
2. On prend les 10 premiers messages
3. On appelle l'IA pour générer un résumé court de ces messages
4. On remplace les 10 messages par le résumé
5. On garde les 10 derniers messages tels quels

**Avantages :**
- Conserve le contexte important de toute la conversation
- Économie de 40-50% des tokens
- Meilleure qualité de conversation longue

**Inconvénients :**
- Très complexe à implémenter
- Coûte des tokens supplémentaires pour générer les résumés
- Risque de perte d'informations dans le résumé
- Nécessite logique sophistiquée

**Quand l'utiliser :**
- Conversations très longues (support client)
- Chatbot complexe nécessitant beaucoup de contexte
- Phase avancée du projet

---

#### Stratégie 3 : Compression intelligente ⭐ **RECOMMANDÉ**

**Principe :**
Combinaison de plusieurs techniques pour optimiser sans perdre le contexte essentiel.

**Règles appliquées :**
1. **Toujours garder le message système** (contexte/instructions initiales de l'IA)
2. **Garder les 2-4 premiers échanges** (contexte d'introduction important)
3. **Garder les 8-10 derniers messages** (contexte récent, le plus important)
4. **Limite maximale de tokens** (ex: 1500-2000 tokens)
5. **Ajustement dynamique** si on dépasse la limite

**Avantages :**
- Économie de 50-60% des tokens
- Garde le contexte essentiel (début + récent)
- Complexité raisonnable
- Équilibre optimal qualité/performance

**Inconvénients :**
- Un peu plus complexe que la limite simple
- Nécessite estimation des tokens

**Quand l'utiliser :**
- Production, usage réel
- Conversations moyennes à longues
- Meilleur compromis pour la plupart des cas

---

### Tableau comparatif des stratégies

| Approche | Tokens économisés | Contexte préservé | Complexité | Recommandation |
|----------|-------------------|-------------------|------------|----------------|
| Aucune optimisation | 0% | ⭐⭐⭐⭐⭐ | ⭐ | ❌ Ne jamais faire |
| Limite simple (12 msg) | 60-70% | ⭐⭐⭐ | ⭐⭐ | ✅ Bon pour démarrer |
| Compression intelligente | 50-60% | ⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐ RECOMMANDÉ |
| Résumé automatique | 40-50% | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⚠️ Avancé uniquement |

---

### Paramètres recommandés pour la compression intelligente

**Constantes à définir :**

```
MAX_HISTORY_MESSAGES = 12
  → Nombre maximum de messages à conserver
  → Représente environ 6 échanges (user + assistant)
  
MAX_TOKENS = 2000
  → Limite maximale de tokens pour l'historique
  → Sécurité supplémentaire si messages très longs
  
KEEP_FIRST_EXCHANGES = 4
  → Garde les 2 premiers échanges (4 messages)
  → Conserve le contexte d'introduction
  
KEEP_RECENT_MESSAGES = 8
  → Garde les 8 derniers messages
  → Conserve le contexte le plus récent et pertinent
```

**Logique d'optimisation :**

1. Si l'historique complet fait moins de 12 messages → On garde tout
2. Si l'historique dépasse 12 messages :
   - On garde le message système (s'il existe)
   - On garde les 2-4 premiers échanges
   - On garde les 8 derniers messages
   - On supprime le milieu
3. On estime les tokens du résultat
4. Si ça dépasse encore 2000 tokens :
   - On réduit davantage (garde seulement les 8 derniers)

---

### Estimation des tokens

**Méthode d'estimation rapide :**

**Règle simple :**
- 1 token ≈ 4 caractères en français
- 1000 caractères ≈ 250 tokens

**Méthode de calcul :**
1. Convertir l'historique en JSON (format texte)
2. Compter le nombre de caractères
3. Diviser par 4

**Exemple :**
```
Historique JSON = 2000 caractères
Estimation tokens = 2000 / 4 = 500 tokens
```

**Précision :**
- Cette estimation est approximative mais suffisante
- Erreur de ±10-20% acceptable pour l'optimisation
- Les APIs fournissent le compte exact après traitement

---

### Monitoring et tracking des tokens

**Informations à logger/tracker :**

**Par requête :**
- Tokens INPUT (historique + question)
- Tokens OUTPUT (réponse)
- Tokens TOTAL
- Service IA utilisé
- Timestamp
- Session ID

**Statistiques globales :**
- Consommation par jour/heure
- Consommation par service IA
- Conversations les plus gourmandes
- Taille moyenne des historiques

**Alertes à mettre en place :**
- Si une conversation dépasse 500 tokens → Warning
- Si on approche des limites quotidiennes → Alerte
- Si taux d'erreur 429 (rate limit) augmente → Alerte

**Bénéfices du monitoring :**
- Anticiper les dépassements de quotas
- Optimiser davantage si nécessaire
- Comprendre les patterns d'utilisation
- Détecter les abus potentiels

---

## 8. Architecture complète du système

### Vue d'ensemble des composants

**Frontend (interface utilisateur) :**
- Page HTML avec zone de chat
- JavaScript pour interactions temps réel
- CSS pour design
- Hébergé sur serveur mutualisé

**Backend (logique serveur) :**
- Scripts PHP sur hébergement mutualisé
- Gestion des requêtes utilisateur
- Coordination avec APIs IA
- Gestion de l'historique

**Base de données :**
- MySQL pour stockage historique
- Table conversations
- Table optionnelle pour statistiques

**APIs externes :**
- Groq (service principal)
- Gemini (service backup)
- Autres si ajoutés plus tard

---

### Flux de fonctionnement complet

**Étape 1 : Visiteur envoie un message**
```
1. Visiteur tape un message dans le chat
2. JavaScript intercepte l'envoi
3. Requête AJAX vers script PHP
```

**Étape 2 : Backend récupère l'historique**
```
4. PHP reçoit le message + session_id
5. Requête SQL : récupère tout l'historique de cette session
6. L'historique est chargé en mémoire
```

**Étape 3 : Optimisation de l'historique**
```
7. Applique compression intelligente
   - Garde message système
   - Garde premiers échanges
   - Garde messages récents
   - Supprime le milieu si trop long
8. Estime les tokens
9. Ajuste si dépasse MAX_TOKENS
```

**Étape 4 : Tentative d'appel API principal (Groq)**
```
10. Prépare requête HTTP vers API Groq
11. Envoie historique optimisé + nouveau message
12. Attend réponse
```

**Étape 5a : Si Groq répond (cas normal)**
```
13. Reçoit réponse de Groq
14. Parse la réponse JSON
15. Passe à l'étape 6
```

**Étape 5b : Si Groq est en limite (erreur 429)**
```
13. Reçoit erreur 429 (rate limit exceeded)
14. Détecte l'erreur
15. Bascule automatiquement sur Gemini
16. Envoie la même requête à Gemini
17. Reçoit réponse de Gemini
18. Passe à l'étape 6
```

**Étape 6 : Sauvegarde en base de données**
```
19. Insère le message utilisateur en BDD
    - session_id
    - role = 'user'
    - content = message
20. Insère la réponse IA en BDD
    - session_id
    - role = 'assistant'
    - content = réponse
    - ai_service = 'groq' ou 'gemini'
    - tokens_used = nombre estimé
```

**Étape 7 : Retour au visiteur**
```
21. PHP renvoie la réponse en JSON
22. JavaScript reçoit la réponse
23. Affiche la réponse dans le chat
24. Interface prête pour prochain message
```

---

### Gestion des sessions

**Identification des conversations :**

**Session ID :**
- Identifiant unique par visiteur/conversation
- Généré au premier message
- Stocké en cookie ou localStorage
- Permet de retrouver l'historique

**Génération du session_id :**
- Format : combinaison date + hash aléatoire
- Exemple : "chat_20250122_a7b3c9d2e1f4"
- Unique et traçable

**Durée de vie :**
- Session active : tant que visiteur sur le site
- Session persistante : peut durer plusieurs jours/semaines
- Nettoyage : suppression des sessions > 30 jours (optionnel)

---

### Sécurité et bonnes pratiques

**Protection des clés API :**
- JAMAIS dans le code JavaScript (visible par tous)
- Stockées dans fichier PHP séparé (hors dossier web)
- Variables d'environnement ou fichier config.php
- Exclusion du fichier de config du Git (.gitignore)

**Validation des entrées utilisateur :**
- Vérifier longueur maximum des messages
- Bloquer caractères spéciaux dangereux
- Limiter fréquence d'envoi (anti-spam)
- Sanitization avant sauvegarde BDD

**Rate limiting côté serveur :**
- Limiter nombre de messages par IP/session
- Exemple : max 30 messages par minute par IP
- Protection contre abus et spam
- Économie des quotas API

**Sanitization des réponses :**
- Échapper HTML dans les réponses IA
- Prévenir injection XSS
- Validation avant affichage

**HTTPS obligatoire :**
- Chiffrement des communications
- Protection des données échangées
- Standard pour APIs modernes

---

## 9. Checklist de mise en œuvre

### Configuration initiale

**Comptes et clés API :**
- [ ] Créer compte Groq sur groq.com
- [ ] Générer clé API Groq
- [ ] Créer compte Google Cloud pour Gemini
- [ ] Activer Gemini API
- [ ] Générer clé API Gemini
- [ ] (Optionnel) Créer compte Cohere
- [ ] (Optionnel) Générer clé API Cohere

**Base de données :**
- [ ] Créer base de données MySQL sur hébergement
- [ ] Noter les identifiants de connexion
- [ ] Créer table conversations (structure fournie)
- [ ] Tester connexion PHP → MySQL
- [ ] Créer index sur session_id

**Hébergement :**
- [ ] Vérifier version PHP (minimum 7.4, idéalement 8.0+)
- [ ] Vérifier extension curl activée
- [ ] Vérifier extension json activée
- [ ] Vérifier extension mysqli ou PDO activée
- [ ] Tester appels HTTPS externes

---

### Développement PHP

**Fichiers à créer :**
- [ ] config.php (clés API, config BDD)
- [ ] chatbot.php (classe principale)
- [ ] api_groq.php (gestion API Groq)
- [ ] api_gemini.php (gestion API Gemini)
- [ ] database.php (gestion BDD)
- [ ] history_manager.php (gestion historique)
- [ ] endpoint.php (point d'entrée AJAX)

**Fonctionnalités à implémenter :**
- [ ] Connexion base de données
- [ ] Fonction récupération historique
- [ ] Fonction sauvegarde message
- [ ] Compression intelligente historique
- [ ] Estimation tokens
- [ ] Appel API Groq
- [ ] Appel API Gemini
- [ ] Système fallback automatique
- [ ] Gestion erreurs 429 (rate limit)
- [ ] Logging des requêtes
- [ ] Tracking tokens consommés

---

### Développement Frontend

**Fichiers à créer :**
- [ ] index.html (page principale)
- [ ] chat.css (styles du chat)
- [ ] chat.js (logique JavaScript)

**Fonctionnalités à implémenter :**
- [ ] Interface chat (messages, input)
- [ ] Génération/récupération session_id
- [ ] Envoi message via AJAX
- [ ] Affichage réponse
- [ ] Indicateur "IA en train d'écrire..."
- [ ] Gestion erreurs réseau
- [ ] Scroll automatique vers bas
- [ ] (Optionnel) Mise en forme Markdown des réponses

---

### Optimisations

**Compression historique :**
- [ ] Définir MAX_HISTORY_MESSAGES = 12
- [ ] Définir MAX_TOKENS = 2000
- [ ] Implémenter fonction estimateTokens()
- [ ] Implémenter fonction prepareForAI()
- [ ] Implémenter fonction smartTrim() (optionnel)

**Performance :**
- [ ] Activer cache PHP opcache
- [ ] Minimiser fichiers CSS/JS
- [ ] Utiliser CDN pour bibliothèques externes
- [ ] Index BDD optimisés

---

### Sécurité

**Protection API :**
- [ ] Clés API dans fichier séparé hors web root
- [ ] Fichier config.php exclu de Git (.gitignore)
- [ ] Vérification origin/referer dans endpoint.php

**Protection utilisateur :**
- [ ] Validation longueur messages (max 500-1000 caractères)
- [ ] Rate limiting : max 30 messages/minute par IP
- [ ] Sanitization SQL (requêtes préparées)
- [ ] Échappement HTML dans affichage

**HTTPS :**
- [ ] Certificat SSL activé sur hébergement
- [ ] Force HTTPS dans .htaccess
- [ ] Vérification HTTPS dans appels API

---

### Tests

**Tests fonctionnels :**
- [ ] Test conversation simple (3-4 échanges)
- [ ] Test conversation longue (15+ échanges)
- [ ] Test basculement Groq → Gemini (simuler limite)
- [ ] Test reprise conversation après rechargement page
- [ ] Test historique correctement sauvegardé
- [ ] Test compression historique fonctionne
- [ ] Test affichage messages corrects

**Tests limites :**
- [ ] Test rate limiting fonctionne
- [ ] Test message trop long refusé
- [ ] Test erreur réseau gérée
- [ ] Test API indisponible gérée
- [ ] Test toutes APIs en limite

**Tests sécurité :**
- [ ] Test injection SQL bloquée
- [ ] Test XSS bloquée
- [ ] Test spam bloqué
- [ ] Test accès direct endpoint.php

---

### Monitoring et production

**Logs :**
- [ ] Logger toutes les requêtes API
- [ ] Logger erreurs 429
- [ ] Logger consommation tokens
- [ ] Fichier logs rotatif (ne pas remplir disque)

**Statistiques :**
- [ ] Dashboard consommation tokens/jour
- [ ] Graphique répartition Groq/Gemini
- [ ] Nombre conversations/jour
- [ ] Durée moyenne conversations

**Alertes :**
- [ ] Alerte si proche limite quotas
- [ ] Alerte si taux erreur élevé
- [ ] Alerte si temps réponse lent

**Maintenance :**
- [ ] Script nettoyage vieilles conversations (>30j)
- [ ] Backup base de données régulier
- [ ] Mise à jour clés API si nécessaire

---

## 10. Estimations de capacité finale

### Avec configuration optimisée (Groq + Gemini)

**Capacité théorique (quotas cumulés) :**
- 15 900 requêtes par jour
- 45 requêtes par minute

**Capacité réelle (avec optimisation historique) :**

**Conversations courtes (3-4 échanges, ~200 tokens) :**
- Environ 16 000 conversations par jour
- Largement dans les limites

**Conversations moyennes (10-15 échanges, ~500 tokens) :**
- Environ 4 000-6 000 conversations par jour
- Très confortable

**Conversations longues (30+ échanges, ~1000 tokens) :**
- Environ 1 000-2 000 conversations par jour
- Encore très correct

---

### Évolution et scalabilité

**Phase 1 : Démarrage (gratuit)**
- Groq + Gemini uniquement
- 100% gratuit
- Capacité : plusieurs milliers de conversations/jour
- Suffisant pour 95% des sites

**Phase 2 : Croissance (si nécessaire)**
- Ajout Cohere en 3ème backup
- Ajout Mistral AI si besoin français premium
- Toujours gratuit avec quotas cumulés
- Capacité multipliée

**Phase 3 : Production intensive (si forte croissance)**
- Passage plan payant sur service principal
- Coût estimé : 5-20€/mois pour site moyen
- Capacité quasi-illimitée

---

## 11. Points d'attention importants

### Limites techniques à connaître

**L'IA ne peut pas :**
- Accéder à des bases de données externes en temps réel
- Naviguer sur internet (sauf si API spécifique fournie)
- Exécuter du code ou faire des calculs complexes précis
- Conserver des informations entre sessions sans historique
- Garantir 100% d'exactitude factuelle

**L'IA peut :**
- Converser naturellement
- Répondre à des questions générales
- Aider à résoudre des problèmes
- Fournir des explications
- S'adapter au contexte de la conversation

---

### Gestion des attentes utilisateurs

**Temps de réponse :**
- Groq : très rapide (1-3 secondes)
- Gemini : rapide (2-5 secondes)
- Afficher indicateur de chargement important

**Qualité des réponses :**
- Peut varier entre modèles
- Tester et ajuster si nécessaire
- Prévoir message système personnalisé

**Limitations à communiquer :**
- Informer utilisateurs que c'est une IA
- Pas d'accès temps réel à données externes
- Peut faire des erreurs
- Prévoir disclaimer approprié

---

### Conformité et légalité

**RGPD (si visiteurs européens) :**
- Informer de la collecte des conversations
- Permettre suppression des données
- Politique de confidentialité
- Durée conservation limitée

**Modération du contenu :**
- Les APIs ont filtres intégrés
- Prévoir gestion contenus inappropriés
- Possibilité blacklist mots-clés

**Responsabilité :**
- L'IA peut générer contenu incorrect
- Ajouter disclaimer approprié
- Ne pas utiliser pour conseil médical/légal/financier sans précautions

---

## 12. Ressources et documentation

### Documentation officielle des APIs

**Groq :**
- Documentation : https://console.groq.com/docs
- Playground : https://console.groq.com/playground
- Modèles disponibles : https://console.groq.com/docs/models

**Gemini (Google) :**
- Documentation : https://ai.google.dev/docs
- API Reference : https://ai.google.dev/api
- Quickstart : https://ai.google.dev/tutorials/quickstart

**Cohere :**
- Documentation : https://docs.cohere.com/
- API Reference : https://docs.cohere.com/reference/
- Playground : https://dashboard.cohere.com/playground

**Mistral AI :**
- Documentation : https://docs.mistral.ai/
- API : https://docs.mistral.ai/api/
- Modèles : https://docs.mistral.ai/getting-started/models/

---

### Communautés et support

**Forums et discussions :**
- Stack Overflow (tag : groq, gemini-api, chatbot)
- Reddit : r/ArtificialIntelligence, r/ChatGPT
- Discord communautaires des différents services

**Tutoriels vidéo :**
- YouTube : rechercher "Groq API tutorial"
- YouTube : rechercher "Gemini API tutorial"
- Cours Udemy/Coursera sur chatbots

---

## 13. Prochaines étapes recommandées

### Ordre de mise en œuvre suggéré

**Semaine 1 : Préparation**
1. Créer comptes Groq et Gemini
2. Générer clés API
3. Étudier documentation de base
4. Préparer base de données

**Semaine 2 : Développement backend**
5. Créer structure fichiers PHP
6. Implémenter connexion BDD
7. Implémenter appel API Groq
8. Tester appel basique

**Semaine 3 : Historique et optimisation**
9. Implémenter sauvegarde historique
10. Implémenter récupération historique
11. Implémenter compression intelligente
12. Tester conversations longues

**Semaine 4 : Frontend et finitions**
13. Créer interface chat
14. Connecter frontend ↔ backend
15. Implémenter fallback Gemini
16. Tests complets

**Semaine 5 : Production**
17. Sécurisation finale
18. Monitoring et logs
19. Mise en ligne
20. Tests utilisateurs réels

---

## Conclusion

Ce guide complet fournit toutes les informations théoriques et stratégiques nécessaires pour mettre en place un chatbot IA sur hébergement mutualisé.

**Points clés à retenir :**

✅ **Architecture hybride** : Site mutualisé + APIs externes
✅ **Multi-API** : Groq (principal) + Gemini (backup) pour cumul quotas
✅ **Historique** : Stockage BDD obligatoire pour contexte conversationnel
✅ **Optimisation tokens** : Compression intelligente économise 50-60%
✅ **Capacité** : 16 000 conversations/jour gratuitement
✅ **Scalable** : Évolution facile selon croissance

Le projet est techniquement réalisable, économiquement viable (gratuit au démarrage), et offre une excellente base pour un chatbot professionnel.

---

**Document créé pour Bruno - Janvier 2025**
**À utiliser avec Claude dans VSCode pour implémentation pratique**
