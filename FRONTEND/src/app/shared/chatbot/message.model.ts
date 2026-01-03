export interface ChatMessage {
    id: string;
    content: string;
    sender: 'user' | 'bot';
    timestamp: Date;
    suggestions?: string[];
    actions?: ChatAction[];
}

export interface ChatAction {
    label: string;
    route: string;
}

export interface ChatResponse {
    success: boolean;
    message: string;
    suggestions?: string[];
    actions?: ChatAction[];
    timestamp: string;
}
