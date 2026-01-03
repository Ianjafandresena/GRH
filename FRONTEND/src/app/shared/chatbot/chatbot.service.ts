import { Injectable, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, catchError, map, of } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ChatMessage, ChatResponse, ChatAction } from './message.model';

/**
 * ChatbotService - Service Angular moderne avec signals
 * Gestion communication avec backend chatbot
 */
@Injectable({ providedIn: 'root' })
export class ChatbotService {
    private readonly http = inject(HttpClient);
    private readonly apiUrl = environment.apiUrl + '/chatbot';

    // Signals pour état réactif
    messages = signal<ChatMessage[]>([]);
    isOpen = signal(false);
    isTyping = signal(false);

    private empCode: number | null = null;

    constructor() {
        this.loadWelcomeMessage();
    }

    /**
     * Définir employé connecté
     */
    setEmployee(empCode: number) {
        this.empCode = empCode;
    }

    /**
     * Toggle chatbot ouvert/fermé
     */
    toggle() {
        this.isOpen.update(open => !open);
    }

    /**
     * Envoyer message
     */
    sendMessage(content: string): void {
        if (!content.trim()) return;

        // Message utilisateur
        const userMsg: ChatMessage = {
            id: this.generateId(),
            content,
            sender: 'user',
            timestamp: new Date()
        };

        this.addMessage(userMsg);
        this.isTyping.set(true);

        // Appel API backend
        this.http.post<ChatResponse>(`${this.apiUrl}/message`, {
            message: content,
            emp_code: this.empCode
        }).pipe(
            catchError(err => {
                console.error('[Chatbot] Erreur API:', err);
                // Fail-safe: réponse locale par défaut
                return of({
                    success: false,
                    message: "Désolé, une erreur s'est produite. Veuillez réessayer.",
                    suggestions: ['Demandes en attente', 'Remboursements', 'Aide'],
                    timestamp: new Date().toISOString()
                } as ChatResponse);
            })
        ).subscribe(response => {
            this.isTyping.set(false);

            const botMsg: ChatMessage = {
                id: this.generateId(),
                content: response.message,
                sender: 'bot',
                timestamp: new Date(),
                suggestions: response.suggestions || [],
                actions: response.actions || []
            };

            this.addMessage(botMsg);
        });
    }

    /**
     * Envoyer suggestion rapide
     */
    sendSuggestion(text: string) {
        this.sendMessage(text);
    }

    /**
     * Charger suggestions personnalisées
     */
    loadSuggestions(): Observable<string[]> {
        if (!this.empCode) {
            return of(['Solde de ANDRIA', 'Demandes en attente', 'Aide']);
        }

        return this.http.get<{ success: boolean, suggestions: string[] }>(`${this.apiUrl}/suggestions`, {
            params: { emp_code: this.empCode.toString() }
        }).pipe(
            map(res => res.suggestions || []),
            catchError(() => of(['Solde de ANDRIA', 'Demandes en attente']))
        );
    }

    /**
     * Effacer historique
     */
    clearHistory() {
        this.messages.set([]);
        this.loadWelcomeMessage();
    }

    /**
     * Ajouter message à l'historique
     */
    private addMessage(msg: ChatMessage) {
        this.messages.update(msgs => [...msgs, msg]);
    }

    /**
     * Message de bienvenue
     */
    private loadWelcomeMessage() {
        const welcome: ChatMessage = {
            id: this.generateId(),
            content: "👋 Bonjour ! Je suis votre assistant RH intelligent. Comment puis-je vous aider ?",
            sender: 'bot',
            timestamp: new Date(),
            suggestions: ['Demandes en attente', 'Remboursements', 'Comment créer congé ?']
        };
        this.addMessage(welcome);
    }

    /**
     * Générer ID unique
     */
    private generateId(): string {
        return Date.now().toString(36) + Math.random().toString(36).substring(2);
    }
}
