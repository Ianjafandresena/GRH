import { Component, inject, effect, ViewChild, ElementRef, AfterViewChecked } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { MatIconModule } from '@angular/material/icon';
import { ChatbotService } from './chatbot.service';
import { ChatMessage } from './message.model';

/**
 * ChatbotComponent - Composant standalone moderne
 * Widget floating accessible sur toutes les pages
 */
@Component({
    selector: 'app-chatbot',
    standalone: true,
    imports: [CommonModule, FormsModule, MatIconModule],
    templateUrl: './chatbot.component.html',
    styleUrls: ['./chatbot.component.scss']
})
export class ChatbotComponent implements AfterViewChecked {
    private readonly chatService = inject(ChatbotService);
    private readonly router = inject(Router);

    @ViewChild('messagesContainer') private messagesContainer?: ElementRef;

    // Signals réactifs du service
    messages = this.chatService.messages;
    isOpen = this.chatService.isOpen;
    isTyping = this.chatService.isTyping;

    currentMessage = '';
    showSettings = false;  // Menu paramètres
    navigationMode = false; // Mode navigation
    private shouldScrollToBottom = false;

    constructor() {
        // Charger mode navigation depuis localStorage
        const savedMode = localStorage.getItem('chatbot_navigation_mode');
        if (savedMode === 'true') {
            this.navigationMode = true;
        }

        // Effect pour auto-scroll quand nouveau message
        effect(() => {
            if (this.messages().length > 0) {
                this.shouldScrollToBottom = true;

                // Auto-navigation si mode activé  
                if (this.navigationMode) {
                    setTimeout(() => this.checkAutoNavigation(), 0);
                }
            }
        });
    }

    ngAfterViewChecked() {
        if (this.shouldScrollToBottom) {
            this.scrollToBottom();
            this.shouldScrollToBottom = false;
        }
    }

    /**
     * Toggle chatbot
     */
    toggle() {
        this.chatService.toggle();
        this.showSettings = false;
    }

    /**
     * Toggle menu paramètres
     */
    toggleSettings() {
        this.showSettings = !this.showSettings;
    }

    /**
     * Toggle mode navigation
     */
    toggleNavigationMode() {
        this.navigationMode = !this.navigationMode;

        // Sauvegarder dans localStorage
        localStorage.setItem('chatbot_navigation_mode', this.navigationMode.toString());

        console.log('📍 Navigation mode:', this.navigationMode ? 'ACTIVÉ' : 'DÉSACTIVÉ');

        // Si on vient d'activer et qu'il y a une action auto dans le dernier message
        if (this.navigationMode) {
            this.checkAutoNavigation();
        }
    }

    /**
     * Vérifier et exécuter auto-navigation
     */
    private checkAutoNavigation() {
        const messages = this.messages();
        if (messages.length === 0) return;

        // Dernier message du bot
        const lastBotMsg = [...messages].reverse().find(m => m.sender === 'bot');

        if (lastBotMsg?.actions && lastBotMsg.actions.length > 0) {
            // Chercher action avec flag 'auto'
            const autoAction = lastBotMsg.actions.find((a: any) => a.auto === true);

            if (autoAction) {
                console.log('🚀 Auto-navigation vers:', autoAction.route);

                // Petite delay pour UX (montrer le message avant navigation)
                setTimeout(() => {
                    this.router.navigate([autoAction.route]);
                    // Chatbot reste ouvert pour continuer la conversation
                }, 1500);
            }
        }
    }

    /**
     * Envoyer message
     */
    send() {
        if (!this.currentMessage.trim()) return;

        this.chatService.sendMessage(this.currentMessage);
        this.currentMessage = '';
    }

    /**
     * Envoyer suggestion
     */
    sendSuggestion(text: string) {
        this.chatService.sendSuggestion(text);
    }

    /**
     * Exécuter action (navigation)
     */
    executeAction(action: any) {
        console.log('🎯 Navigation manuelle vers:', action.route);
        this.router.navigate([action.route]);
        this.toggle(); // Fermer chatbot
    }

    /**
     * Effacer historique
     */
    clearChat() {
        if (confirm('Voulez-vous effacer l\'historique de la conversation ?')) {
            this.chatService.clearHistory();
        }
    }

    /**
     * Scroll auto en bas
     */
    private scrollToBottom() {
        try {
            if (this.messagesContainer) {
                this.messagesContainer.nativeElement.scrollTop =
                    this.messagesContainer.nativeElement.scrollHeight;
            }
        } catch (err) {
            console.error('[Chatbot] Erreur scroll:', err);
        }
    }

    /**
     * Formater timestamp
     */
    formatTime(date: Date): string {
        return new Date(date).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    }
}
