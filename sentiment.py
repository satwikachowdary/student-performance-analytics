import sys
import json
import re

def analyze_sentiment(text):
    # 1. Define Sentiment Dictionary (Sleep & Health focused)
    positive_words = {
        'good', 'great', 'rested', 'refreshed', 'energized', 'happy', 'calm', 
        'peaceful', 'deep', 'sound', 'amazing', 'focused', 'alert', 'ready',
        'better', 'well', 'awake', 'active', 'strong', 'clear'
    }
    
    negative_words = {
        'bad', 'poor', 'tired', 'exhausted', 'groggy', 'stressed', 'anxious',
        'sad', 'depressed', 'awake', 'tossing', 'turning', 'insomnia', 'pain',
        'weak', 'foggy', 'drained', 'sleepy', 'fatigue', 'terrible', 'worst',
        'nightmare', 'broken', 'difficult'
    }
    
    # 2. Tokenize and clean
    text = text.lower()
    words = re.findall(r'\b\w+\b', text)
    
    if not words:
        return 0.0
    
    # 3. Score
    score = 0
    matched_words = []
    
    for word in words:
        if word in positive_words:
            score += 1
            matched_words.append(word)
        elif word in negative_words:
            score -= 1
            matched_words.append(word)
            
    # Normalize score roughly between -1 and 1
    # We assume a sentence rarely has more than 5 emotively charged words
    normalized_score = max(-1.0, min(1.0, score / 5.0))
    
    return json.dumps({
        "score": normalized_score,
        "raw_score": score,
        "matched": matched_words
    })

if __name__ == "__main__":
    if len(sys.argv) > 1:
        input_text = sys.argv[1]
        print(analyze_sentiment(input_text))
    else:
        print(json.dumps({"score": 0, "error": "No input text provided"}))
