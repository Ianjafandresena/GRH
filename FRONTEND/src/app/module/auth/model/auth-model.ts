export interface Admin {
  id: string;
  username: string;
  nom: string;
  prenom: string;
  role: string;
}

export interface AuthResponse {
  status: string;
  message: string;
  admin: Admin;  // Le token est dans le cookie
}

export interface LoginCredentials {
  username: string;
  password: string;
}
